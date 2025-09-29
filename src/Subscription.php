<?php
namespace Rebel\BCApi2;

use Carbon\Carbon;

class Subscription extends Entity
{
    protected string $primaryKey = 'subscriptionId';

    public string $subscriptionId {
        get => $this->get('subscriptionId');
    }

    public string $notificationUrl {
        set {
            $this->set('notificationUrl', $value);
        }
        get => $this->get('notificationUrl');
    }

    public string $resource {
        set {
            $this->set('resource', $value);
        }
        get => $this->get('resource');
    }

    public int $timestamp {
        get => $this->get('timestamp');
    }

    public string $userId {
        get => $this->get('userId');
    }

    public Carbon $lastModifiedDateTime {
        get => $this->getAsDateTime('lastModifiedDateTime');
    }

    public ?string $clientState {
        set {
            $this->set('clientState', $value);
        }
        get => $this->get('clientState');
    }

    public Carbon $expirationDateTime {
        get => $this->getAsDateTime('expirationDateTime');
    }

    public Carbon $systemCreatedAt {
        get => $this->getAsDateTime('systemCreatedAt');
    }

    public string $systemCreatedBy {
        get => $this->get('systemCreatedBy');
    }

    public Carbon $systemModifiedAt {
        get => $this->getAsDateTime('systemModifiedAt');
    }

    public string $systemModifiedBy {
        get => $this->get('systemModifiedBy');
    }
    
    public function isExpired(): bool
    {
        return $this->expirationDateTime->isFuture();
    }
}
