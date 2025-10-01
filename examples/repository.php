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

// find some Sales Orders
/** @var SalesOrder\Record $salesOrder */
$salesOrders = $repository->findBy([], 'orderDate DESC', 5, null, [ 'salesOrderLines', 'customer' ]);
foreach ($salesOrders as $salesOrder) {
    echo ' - ' . $salesOrder->getNumber() . " @ " . $salesOrder->getOrderDate()->toDateString() . ": " . $salesOrder->getTotalAmountIncludingTax() . ' ' . $salesOrder->getCurrencyCode() . ' (' . $salesOrder->getStatus() . ")\n";
}

// create a Sales Order
$salesOrder = new SalesOrder\Record([
    "externalDocumentNumber" => "TEST-001",
    "customerNumber" => "NA0007",
], [ 'salesOrderLines' ]);

$salesLines = $salesOrder->getSalesOrderLines();
$salesLines[] = new SalesOrderLine\Record([
    "sequence" => 10000,
    "itemId" => "b3c285a5-f12b-f011-9a4a-7c1e5275406f",
    "quantity" => 10,
]);

$salesLines[] = new SalesOrderLine\Record([
    "lineType" => "Item",
    "lineObjectNumber" => "1120",
    "quantity" => 20
]);

$repository->create($salesOrder);
echo 'CREATED: ' . $salesOrder->getNumber() . " @ " . $salesOrder->getOrderDate()->toDateString() . ": " . $salesOrder->getTotalAmountIncludingTax() . ' ' . $salesOrder->getCurrencyCode() . ' (' . $salesOrder->getStatus() . ")\n";

// get the created Sales Order
$salesOrder = $repository->get($salesOrder->getId(), [ 'salesOrderLines', 'customer' ]);
echo 'RETRIEVED: ' . $salesOrder->getNumber() . " @ " . $salesOrder->getOrderDate()->toDateString() . ": " . $salesOrder->getTotalAmountIncludingTax() . ' ' . $salesOrder->getCurrencyCode() . ' (' . $salesOrder->getStatus() . ")\n";

// delete the Sales Order
$repository->delete($salesOrder);
echo 'DELETED: ' . $salesOrder->getNumber() . "\n";