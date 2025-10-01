<?php
use Rebel\BCApi2\Subscription;

chdir(__DIR__ . '/../');

// connect using config/config.php credentials
$client = include(__DIR__ . '/connect/client-credentials.php');
# $client = include(__DIR__ . '/connect/authorization-code.php');

$repository = new Subscription\Repository($client);

$subscription = new Subscription();
$subscription->setNotificationUrl('https://admin.rebel.pl/test/webhook');

$repository->register($subscription, '/contacts');
var_dump($subscription->getSubscriptionId());

$repository->renew($subscription);
echo $subscription->getExpirationDateTime() . "\n";