<?php

namespace Core\Notifications;

interface NotificationInterface
{
    public function sendMessage($message): bool;
}
