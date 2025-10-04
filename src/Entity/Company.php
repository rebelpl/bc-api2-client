<?php
namespace Rebel\BCApi2\Entity;

use Carbon\Carbon;
use Rebel\BCApi2\Client;
use Rebel\BCApi2\Entity;

/**
 * @property-read ?string id
 * @property-read ?string systemVersion
 * @property-read ?int timestamp
 * @property-read ?string name
 * @property-read ?string displayName
 * @property-read ?string businessProfileId
 * @property-read ?Carbon systemCreatedAt
 * @property-read ?string systemCreatedBy
 * @property-read ?Carbon systemModifiedAt
 * @property-read ?string systemModifiedBy
*/
class Company extends Entity
{
    protected $primaryKey = 'id';
    
    protected $casts = [
        'id' => 'guid',
        'systemCreatedAt' => 'datetime',
        'systemCreatedBy' => 'guid',
        'systemModifiedAt' => 'datetime',
        'systemModifiedBy' => 'guid',
    ];

    /**
     * @return Repository<Company>
     */
    public static function getRepository(Client $client, ?string $entityClass = null): Repository
    {
        return new Repository($client, 'companies', $entityClass ?? static::class, false);
    }
}