<?php
namespace Rebel\BCApi2;

use Carbon\Carbon;

class Subscription extends Entity
{
    protected $primaryKey = 'subscriptionId';
    
    public function getSubscriptionId()
    {
        return $this->get('subscriptionId');
    }

    function getNotificationUrl(): ?string
    {
        return $this->get('notificationUrl');
    }

    function setNotificationUrl(?string $value): self
    {
        $this->set('notificationUrl', $value);
        return $this;
    }

    function getResource(): ?string
    {
        return $this->get('resource');
    }

    function setResource(?string $value): self
    {
        $this->set('resource', $value);
        return $this;
    }

    function getTimestamp(): ?int
    {
        return $this->get('timestamp');
    }

    function getUserId(): ?string
    {
        return $this->get('userId');
    }

    function getLastModifiedDateTime(): ?Carbon
    {
        return $this->getAsDateTime('lastModifiedDateTime');
    }

    function getClientState(): ?string
    {
        return $this->get('clientState');
    }

    function setClientState(?string $value): self
    {
        $this->set('clientState', $value);
        return $this;
    }

    function getExpirationDateTime(): ?Carbon
    {
        return $this->getAsDateTime('expirationDateTime');
    }

    function getSystemCreatedAt(): ?Carbon
    {
        return $this->getAsDateTime('systemCreatedAt');
    }

    function getSystemCreatedBy(): ?string
    {
        return $this->get('systemCreatedBy');
    }

    function getSystemModifiedAt(): ?Carbon
    {
        return $this->getAsDateTime('systemModifiedAt');
    }

    function getSystemModifiedBy(): ?string
    {
        return $this->get('systemModifiedBy');
    }
    
    public function isExpired(): bool
    {
        return $this->getExpirationDateTime()->isFuture();
    }
}
