<?php
namespace Rebel\Test\BCApi2\Subscription;

use PHPUnit\Framework\TestCase;
use Rebel\BCApi2\Subscription;

class NotificationTest extends TestCase
{
    private array $notifications = [];
    
    public function setUp(): void
    {
        $this->notifications = Subscription\Notification::createSetFromStream('tests/files/notifications.json');
    }
    
    public function testNotificationHasChangeType(): void
    {
        $this->assertCount(1, $this->notifications);
        foreach ($this->notifications as $notification) {
            $this->assertTrue($notification instanceof Subscription\Notification);
            $this->assertEquals('updated', $notification->changeType);
        }
    }
}