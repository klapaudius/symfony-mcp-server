<?php

namespace KLP\KlpMcpServer\Utils;

use KLP\KlpMcpServer\Data\Requests\NotificationData;
use KLP\KlpMcpServer\Data\Requests\RequestData;

class DataUtil
{
    public static function makeRequestData(string $clientId, array $message): RequestData|NotificationData|null
    {
        $data = null;
        if (isset($message['method'])) {
            if (isset($message['id'])) {
                $data = RequestData::fromArray(data: $message);
            } else {
                $data = NotificationData::fromArray(data: array_merge(['clientId' => $clientId], $message));
            }
        } elseif (isset($message['result'])) {
            $data = NotificationData::fromArray(data: array_merge(['clientId' => $clientId], $message));
        }

        return $data;
    }
}
