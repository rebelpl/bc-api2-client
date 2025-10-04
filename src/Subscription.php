<?php
namespace Rebel\BCApi2;

use Carbon\Carbon;

/**
 * @property ?string subscriptionId
 * @property ?string notificationUrl
 * @property ?string resource
 * @property ?int timestamp
 * @property ?string userId
 * @property-read ?Carbon lastModifiedDateTime
 * @property ?string clientState
 * @property ?Carbon expirationDateTime
 * @property-read ?Carbon systemCreatedAt
 * @property-read ?string systemCreatedBy
 * @property-read ?Carbon systemModifiedAt
 * @property-read ?string systemModifiedBy
 */
class Subscription extends Entity
{
    protected $primaryKey = 'subscriptionId';

    protected $casts = [
        'userId' => 'guid',
        'lastModifiedDateTime' => 'datetime',
        'expirationDateTime' => 'datetime',
        'systemCreatedAt' => 'datetime',
        'systemCreatedBy' => 'guid',
        'systemModifiedAt' => 'datetime',
        'systemModifiedBy' => 'guid',
    ];

    public function isExpired(): bool
    {
        return $this->expirationDateTime->isFuture();
    }
    
    /**
     * @return Subscription\Repository<Subscription>
     */
    public static function getRepository(Client $client, ?string $entityClass = null): Subscription\Repository
    {
        return new Subscription\Repository($client, $entityClass ?? static::class);
    }
}