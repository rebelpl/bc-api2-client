<?php
use Rebel\BCApi2\Entity\Repository;
use Rebel\BCApi2\Entity\Item;

chdir(__DIR__ . '/../');

// connect using tests/config.php credentials
$client = include(__DIR__ . '/connect/client-credentials.php');
# $client = include(__DIR__ . '/connect/authorization-code.php');

/** @var Repository<Item\Record> $repository */
$repository = new Item\Repository($client);
echo $repository->getBaseUrl() . "\n";

$item = $repository->findOneBy([ 'number' => '100000' ]);
echo ' - ' . $item->number . "\n";

$item->expandWith('picture', $client);
echo ' - ' . $item->picture->contentType . "\n";

$picture = file_get_contents('tests/files/picture.png');
$item->picture->pictureContent->uploadWith($client, $picture, $item->picture->getETag());
echo ' - ' . $item->picture->contentType . "\n";
