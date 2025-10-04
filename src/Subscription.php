<?php
namespace Rebel\BCApi2;

use Carbon\Carbon;

class Subscription extends Entity
{
    protected string $primaryKey = 'subscriptionId';

    public ?string $subscriptionId {
        get => $this->get('subscriptionId');
    }

    public ?string $notificationUrl {
        set {
            $this->set('notificationUrl', $value);
        }
        get => $this->get('notificationUrl');
    }

    public ?string $resource {
        set {
            $this->set('resource', $value);
        }
        get => $this->get('resource');
    }

    public ?int $timestamp {
        get => $this->get('timestamp');
    }

    public ?string $userId {
        get => $this->get('userId', 'guid');
    }

    public ?Carbon $lastModifiedDateTime {
        get => $this->get('lastModifiedDateTime', 'datetime');
    }

    public ?string $clientState {
        set {
            $this->set('clientState', $value);
        }
        get => $this->get('clientState');
    }

    public ?Carbon $expirationDateTime {
        get => $this->get('expirationDateTime', 'datetime');
    }

    public ?Carbon $systemCreatedAt {
        get => $this->get('systemCreatedAt', 'datetime');
    }

    public ?string $systemCreatedBy {
        get => $this->get('systemCreatedBy', 'guid');
    }

    public ?Carbon $systemModifiedAt {
        get => $this->get('systemModifiedAt', 'datetime');
    }

    public ?string $systemModifiedBy {
        get => $this->get('systemModifiedBy', 'guid');
    }
    
    public function isExpired(): bool
    {
        return $this->expirationDateTime->isFuture();
    }

    /**
     * @return Subscription\Repository<Subscription>
     */
    public static function getRepository(Client $client, ?string $entityClass = null): Subscription\Repository
    {
        return new Subscription\Repository($client, entityClass: $entityClass ?? static::class);
    }
}