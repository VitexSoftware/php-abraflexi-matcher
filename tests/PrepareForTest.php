<?php

/**
 * php-abraflexi-matecher - Prepare Testing Data
 * 
 * @copyright (c) 2018, Vítězslav Dvořák
 */
define('EASE_LOGGER', 'syslog|console');
$shared = Shared::singleton();
if (file_exists('../vendor/autoload.php')) {
    if (file_exists('../.env')) {
        $shared->loadConfig('../.env', true);
    }
    new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');
} else {
    require_once './vendor/autoload.php';
    if (file_exists('./.env')) {
        $shared->loadConfig('./.env', true);
    }
    new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), './i18n', 'abraflexi-matcher');
}

if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}
new \Ease\Locale($shared->getConfigValue('MATCHER_LOCALIZE'), '../i18n', 'abraflexi-matcher');

function unc($code) {
    return \AbraFlexi\AbraFlexiRO::uncode($code);
}

/**
 * Prepare Testing Invoice
 * 
 * @param array $initialData
 * 
 * @return \AbraFlexi\FakturaVydana
 */
function makeInvoice($initialData = [], $dayBack = 1, $evidence = 'vydana') {
    $yesterday = new \DateTime();
    $yesterday->modify('-' . $dayBack . ' day');
    $testCode = 'INV_' . \Ease\Functions::randomString();
    $invoice = new \AbraFlexi\FakturaVydana(null,
            ['evidence' => 'faktura-' . $evidence]);
    $invoice->takeData(array_merge([
        'kod' => $testCode,
        'varSym' => \Ease\Functions::randomNumber(1111, 9999),
        'specSym' => \Ease\Functions::randomNumber(111, 999),
        'bezPolozek' => true,
        'popis' => 'php-abraflexi-matcher Test invoice',
        'datVyst' => \AbraFlexi\AbraFlexiRO::dateToFlexiDate($yesterday),
        'typDokl' => \AbraFlexi\AbraFlexiRO::code('FAKTURA')
                    ], $initialData));
    if ($invoice->sync()) {
        $invoice->addStatusMessage($invoice->getApiURL() . ' ' . unc($invoice->getDataValue('typDokl')) . ' ' . unc($invoice->getRecordIdent()) . ' ' . unc($invoice->getDataValue('sumCelkem')) . ' ' . unc($invoice->getDataValue('mena')),
                'success');
    } else {
        $invoice->addStatusMessage(json_encode($invoice->getData()), 'debug');
    }

    return $invoice;
}

/**
 * Prepare testing payment
 * 
 * @param array $initialData
 * 
 * @return \AbraFlexi\Banka
 */
function makePayment($initialData = [], $dayBack = 1) {
    $yesterday = new \DateTime();
    $yesterday->modify('-' . $dayBack . ' day');

    $testCode = 'PAY_' . \Ease\Functions::randomString();

    $payment = new \AbraFlexi\Banka($initialData);

    $payment->takeData(array_merge([
        'kod' => $testCode,
        'banka' => 'code:HLAVNI',
        'typPohybuK' => 'typPohybu.prijem',
        'popis' => 'php-abraflexi-matcher Test bank record',
        'varSym' => \Ease\Functions::randomNumber(1111, 9999),
        'specSym' => \Ease\Functions::randomNumber(111, 999),
        'bezPolozek' => true,
        'datVyst' => \AbraFlexi\AbraFlexiRO::dateToFlexiDate($yesterday),
        'typDokl' => \AbraFlexi\AbraFlexiRO::code('STANDARD')
                    ], $initialData));
    if ($payment->sync()) {
        $payment->addStatusMessage($payment->getApiURL() . ' ' . unc($payment->getDataValue('typPohybuK')) . ' ' . unc($payment->getRecordIdent()) . ' ' . unc($payment->getDataValue('sumCelkem')) . ' ' . unc($payment->getDataValue('mena')),
                'success');
    } else {
        $payment->addStatusMessage(json_encode($payment->getData()), 'debug');
    }
    return $payment;
}

$labeler = new AbraFlexi\Stitek();
$labeler->createNew('PREPLATEK', ['banka']);
$labeler->createNew('CHYBIFAKTURA', ['banka']);
$labeler->createNew('NEIDENTIFIKOVANO', ['banka']);

$banker = new AbraFlexi\Banka(null, ['evidence' => 'bankovni-ucet']);
if (!$banker->recordExists(['kod' => 'HLAVNI'])) {
    $banker->insertToAbraFlexi(['kod' => 'HLAVNI', 'nazev' => 'Main Account']);
}

$addresar = new AbraFlexi\Evidence(new \AbraFlexi\Adresar(),
        ['typVztahuK' => 'typVztahu.odberDodav', 'relations' => 'bankovniSpojeni']);

$adresser = new \AbraFlexi\Adresar();
//$allAddresses = $adresser->getColumnsFromAbraFlexi(['kod'],
//    ['typVztahuK' => 'typVztahu.odberDodav','relations'=>'bankovniSpojeni']);

$pu = new \AbraFlexi\AbraFlexiRW(['kod' => 9999, 'nazev' => 'TEST Bank'],
        ['evidence' => 'penezni-ustav']);
if (!$pu->recordExists()) {
    $pu->insertToAbraFlexi();
}


$pf = new AbraFlexi\Bricks\ParovacFaktur($shared->configuration);

foreach ($addresar->getEvidenceObjects() as $address) {
    $allAddresses[] = $address->getData();
    if (empty($address->getDataValue('bankovniSpojeni'))) {
        $fap = new AbraFlexi\Banka(['buc' => time(), 'smerKod' => 'code:9999'],
                ['offline' => true]);
        $pf->assignBankAccountToAddress($address, $fap);
        sleep(1);
    }
}

$customer = $allAddresses[array_rand($allAddresses)];

do {
    $firmaA = $allAddresses[array_rand($allAddresses)];
    $bucA = $adresser->getBankAccountNumber(\AbraFlexi\AbraFlexiRO::code($firmaA['kod']));
} while (empty($bucA));
if (!\Ease\Functions::isAssoc($bucA)) {
    $bucA = current($bucA);
}


$adresser->addStatusMessage('Company A: ' . $firmaA['kod']);
do {
    $firmaB = $allAddresses[array_rand($allAddresses)];
    $bucB = $adresser->getBankAccountNumber(\AbraFlexi\AbraFlexiRO::code($firmaB['kod']));
} while (empty($bucB));

if (!\Ease\Functions::isAssoc($bucB)) {
    $bucB = current($bucB);
}

$adresser->addStatusMessage('Company B: ' . $firmaB['kod']);

$firma = \AbraFlexi\AbraFlexiRO::code($customer['kod']);
$buc = $customer['id'] . $customer['id'] . $customer['id'];
$bank = 'code:0300';

for ($i = 0; $i <= constant('DAYS_BACK') + 3; $i++) {
    $banker->addStatusMessage($i . '/' . (constant('DAYS_BACK') + 3));
    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $specSym = \Ease\Functions::randomNumber(111, 999);
    $price = \Ease\Functions::randomNumber(11, 99);

    $invoiceSs = makeInvoice(['varSym' => $varSym, 'specSym' => $specSym, 'sumZklZaklMen' => $price,
        'mena' => 'code:EUR', 'firma' => $firma], $i);
    $paymentSs = makePayment(['specSym' => $specSym, 'sumZklZaklMen' => $price, 'mena' => 'code:EUR',
        'buc' => $buc, 'smerKod' => $bank], $i);

    $invoiceVs = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'firma' => $firma],
            $i);
    $paymentVs = makePayment(['varSym' => $varSym, 'sumZklZakl' => $price, 'buc' => $buc,
        'smerKod' => $bank], $i);

    $dobropis = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => -$price, 'typDokl' => \AbraFlexi\AbraFlexiRO::code('ZDD')],
            $i);

    $zaloha = makeInvoice(['varSym' => $varSym, 'sumZklZakl' => $price, 'typDokl' => \AbraFlexi\AbraFlexiRO::code('ZÁLOHA')],
            $i);

    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $price = \Ease\Functions::randomNumber(11, 99);
    $prijata = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => AbraFlexi\AbraFlexiRW::dateToFlexiDate(new DateTime()),
        'typDokl' => \AbraFlexi\AbraFlexiRO::code((rand(0, 1) == 1) ? 'FAKTURA' : 'ZÁLOHA')],
            $i, 'prijata');
    $paymentin = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej'],
            $i);

    $varSym = \Ease\Functions::randomNumber(1111, 9999);
    $price = \Ease\Functions::randomNumber(11, 99);

    $prijataA = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => AbraFlexi\AbraFlexiRW::dateToFlexiDate(new DateTime()),
        'firma' => \AbraFlexi\AbraFlexiRO::code($firmaA['kod']),
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod'],
        'typDokl' => \AbraFlexi\AbraFlexiRO::code('FAKTURA')], $i, 'prijata');
    $prijataB = makeInvoice(['cisDosle' => $varSym, 'varSym' => $varSym, 'sumZklZakl' => $price,
        'datSplat' => AbraFlexi\AbraFlexiRW::dateToFlexiDate(new DateTime()),
        'firma' => \AbraFlexi\AbraFlexiRO::code($firmaB['kod']),
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod'],
        'typDokl' => \AbraFlexi\AbraFlexiRO::code('FAKTURA')], $i, 'prijata');
    $paymentin1 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucA['buc'], 'smerKod' => $bucA['smerKod']], $i);
    $paymentin2 = makePayment(['varSym' => $varSym, 'sumOsv' => $price, 'typPohybuK' => 'typPohybu.vydej',
        'buc' => $bucB['buc'], 'smerKod' => $bucB['smerKod']], $i);
}
 