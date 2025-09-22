<?php
use Rebel\BCApi2\Entity\Repository;
use Rebel\BCApi2\Entity\Item;

chdir(__DIR__ . '/../');

// connect using tests/config.php credentials
$client = include(__DIR__ . '/connect/client-credentials.php');
# $client = include(__DIR__ . '/connect/authorization-code.php');

/** @var Repository<Item\Record> $repository */
$repository = new Item\Repository($client)->setExpandedByDefault([ 'picture' ]);
echo $repository->getBaseUrl() . "\n";

$item = $repository->findOneBy([ 'number' => '100000' ]);
echo ' - ' . $item->number . "\n";
//echo ' - ' . $item->picture->contentType . "\n";
//file_put_contents('tmp/' . $item->number . '.png', $item->picture->pictureContent->downloadWith($client));
//echo ' - saved to tmp/' . $item->number . '.png' . "\n";

$picture = file_get_contents('tests/files/picture.png');
$item->picture->pictureContent->uploadWith($client, $picture, $item->picture->getETag());
echo ' - ' . $item->picture->contentType . "\n";
