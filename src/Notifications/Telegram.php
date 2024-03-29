<?php

namespace Core\Notifications;

use Core\Logger\Logger;
use GuzzleHttp\Client;

class Telegram implements NotificationInterface
{
    private $chatId;
    private $token;

    public function __construct($chatID, $token)
    {
        $this->chatId = $chatID;
        $this->token  = $token;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendMessage($message): bool
    {
        Logger::info("Notification: ".$message);
        $url      = "https://api.telegram.org/bot".$this->token."/sendMessage?chat_id=".$this->chatId;
        $url      .= "&text=".urlencode($message);

        $http = new Client();
        $result = $http->get($url);

        return $result->getStatusCode() === 200;
    }
}
