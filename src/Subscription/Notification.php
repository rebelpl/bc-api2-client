<?php
namespace Rebel\BCApi2\Subscription;

use Carbon\Carbon;
use Rebel\BCApi2\Entity;

class Notification extends Entity
{
    public string $subscriptionId {
        get => $this->get('subscriptionId');
    }

    public ?string $clientState {
        get => $this->get('clientState');
    }

    public Carbon $expirationDateTime {
        get => $this->getAsDateTime('expirationDateTime');
    }
    
    public string $resource {
        get => $this->get('resource');
    }

    public string $changeType {
        get => $this->get('changeType');
    }

    public Carbon $lastModifiedDateTime {
        get => $this->getAsDateTime('lastModifiedDateTime');
    }
    
    public static function createSetFromStream($input = 'php://input'): array
    {
        $notifications = [];
        $body = json_decode(file_get_contents($input), true);
        foreach ($body['value'] as $data) {
            $notifications[] = new Notification($data);
        }
        
        return $notifications;
    }
}