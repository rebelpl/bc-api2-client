<?php
use Rebel\BCApi2\Client;
use Rebel\OAuth2\Client\Provider;
include_once __DIR__ . '/../../vendor/autoload.php';

$config = include(__DIR__ . '/../../tests/config.php');
return Client\Factory::useClientCredentials(
    new Provider\BusinessCentral([
        'tenantId' => $config['tenantId'],
        'clientId' => $config['clientId'],
        'clientSecret' => $config['clientSecret'],
    ]),
    $config['environment'], '/v2.0', $config['companyId']);
