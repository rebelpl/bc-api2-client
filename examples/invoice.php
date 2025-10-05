<?php
use Rebel\BCApi2\Entity\Repository;
use Rebel\BCApi2\Entity\SalesInvoice;

chdir(__DIR__ . '/../');

// connect using config/config.php credentials
$client = include(__DIR__ . '/connect/client-credentials.php');
# $client = include(__DIR__ . '/connect/authorization-code.php');

$repository = new SalesInvoice\Repository($client)->setExpandedByDefault([ 'pdfDocument' ]);
echo $repository->getBaseUrl() . "\n";

/** @var SalesInvoice\Record $salesInvoice */
$salesInvoice = $repository->findOneBy([ 'status' => 'Open' ]);
echo ' - ' . $salesInvoice->number . "\n";
echo ' - ' . $salesInvoice->pdfDocument->pdfDocumentContent->getUrl();
file_put_contents('tmp/' . $salesInvoice->number . '.pdf', $salesInvoice->pdfDocument->pdfDocumentContent->downloadWith($client));
echo ' - saved to tmp/' . $salesInvoice->number . '.pdf' . "\n";