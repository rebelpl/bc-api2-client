<?php
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Metadata;

chdir(__DIR__ . '/../');
include_once 'vendor/autoload.php';

$filename = 'tests/files/metadata.xml';
$metadata = Metadata\Factory::fromString(file_get_contents($filename));
$generator = new Entity\Generator($metadata);

$files = $generator->generateAllFiles();
$generator->saveFilesTo($files,'build/', true);