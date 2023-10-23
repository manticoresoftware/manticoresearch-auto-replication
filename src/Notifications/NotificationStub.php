<?php

namespace Core\Notifications;

use Core\Logger\Logger;

class NotificationStub implements NotificationInterface
{
    public function sendMessage($message): bool
    {
        Logger::info("Notification: ".$message);
        return false;
    }
}
