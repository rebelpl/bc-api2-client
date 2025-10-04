<?php
namespace Rebel\BCApi2\Subscription;

use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity;
use Rebel\BCApi2\Subscription;

class Repository extends Entity\Repository
{
    public function __construct(Client $client, string $entityClass = Subscription::class)
    {
        parent::__construct($client, 'subscriptions', $entityClass, false);
    }
    
    public function register(Subscription $subscription, string $resource): void
    {
        $subscription->resource = sprintf('api/%s/%s/%s',
            $this->client->getApiRoute(),
            $this->client->getCompanyPath(),
            trim($resource, '/'));
        $this->create($subscription);
    }
    
    public function renew(Subscription $subscription): void
    {
        $this->update($subscription, true);
    }
}