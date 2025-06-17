<?php
use Rebel\BCApi2\Client;
include_once __DIR__ . '/../../vendor/autoload.php';

$config = include(__DIR__ . '/../../tests/config.php');
return Client\Factory::useClientCredentials(
    tenantId: $config['tenantId'],
    clientId: $config['clientId'],
    clientSecret: $config['clientSecret'],
    environment: $config['environment'],
    companyId: $config['companyId']);
