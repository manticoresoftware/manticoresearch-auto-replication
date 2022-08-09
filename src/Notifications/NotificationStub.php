<?php

namespace Core\Notifications;

use Analog\Analog;

class NotificationStub implements NotificationInterface
{
    public function sendMessage($message): bool
    {
        Analog::log("Notification: ".$message);
        return false;
    }
}
