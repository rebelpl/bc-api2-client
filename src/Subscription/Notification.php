<?php
namespace Rebel\BCApi2\Subscription;

use Carbon\Carbon;
use Rebel\BCApi2\Entity;

/**
 * @property-read string $subscriptionId
 * @property-read ?string $clientState
 * @property-read Carbon $expirationDateTime
 * @property-read string $resource
 * @property-read string $changeType
 * @property-read Carbon $lastModifiedDateTime
 */
class Notification extends Entity
{
    public static function createSetFromStream($input = 'php://input'): array
    {
        $notifications = [];
        $body = json_decode(file_get_contents($input), true);
        foreach ($body['value'] as $data) {
            $notifications[] = (new Notification())->loadData($data);
        }
        
        return $notifications;
    }
}