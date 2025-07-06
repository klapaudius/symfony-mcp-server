<?php

namespace KLP\KlpMcpServer\Utils;

use KLP\KlpMcpServer\Data\Requests\NotificationData;
use KLP\KlpMcpServer\Data\Requests\RequestData;
use KLP\KlpMcpServer\Data\Requests\ResponseData;

class DataUtil
{
    public static function makeRequestData(string $clientId, array $message): RequestData|NotificationData|ResponseData|null
    {
        $data = null;
        if (isset($message['method'])) {
            if (isset($message['id'])) {
                $data = RequestData::fromArray(data: $message);
            } else {
                $data = NotificationData::fromArray(data: array_merge(['clientId' => $clientId], $message));
            }
        } elseif (isset($message['result'])) {
            if (isset($message['id'])) {
                $data = ResponseData::fromArray(data: $message);
            } else {
                $data = NotificationData::fromArray(data: array_merge(['clientId' => $clientId], $message));
            }
        }

        return $data;
    }
}
