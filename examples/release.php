<?php
use Rebel\BCApi2\Entity\Repository;
use Rebel\BCApi2\Entity\SalesOrder;
use Rebel\BCApi2\Entity\SalesOrderLine;

chdir(__DIR__ . '/../');

// connect using config/config.php credentials
$client = include(__DIR__ . '/connect/client-credentials.php');
# $client = include(__DIR__ . '/connect/authorization-code.php');

/** @var Repository<SalesOrder\Record> $repository */
$repository = new SalesOrder\Repository($client);
echo $repository->getBaseUrl() . "\n";

$salesOrder = $repository->findOneBy([ 'no' => 'ZS-1516104' ]);
$salesOrder->doAction('Microsoft.NAV.release', $client);