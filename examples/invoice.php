<?php
use Rebel\BCApi2\Entity\Repository;
use Rebel\BCApi2\Entity\SalesInvoice;

chdir(__DIR__ . '/../');

// connect using config/config.php credentials
$client = include(__DIR__ . '/connect/client-credentials.php');
# $client = include(__DIR__ . '/connect/authorization-code.php');

/** @var Repository<SalesInvoice\Record> $repository */
$repository = (new SalesInvoice\Repository($client))->setExpandedByDefault([ 'pdfDocument' ]);
echo $repository->getBaseUrl() . "\n";

$salesInvoice = $repository->findOneBy([ 'status' => 'Open' ]);
echo ' - ' . $salesInvoice->getNumber() . "\n";
echo ' - ' . $salesInvoice->getPdfDocument()->getPdfDocumentContent()->getUrl();
file_put_contents('tmp/' . $salesInvoice->getNumber() . '.pdf', $salesInvoice->getPdfDocument()->getPdfDocumentContent()->downloadWith($client));
echo ' - saved to tmp/' . $salesInvoice->getNumber() . '.pdf' . "\n";