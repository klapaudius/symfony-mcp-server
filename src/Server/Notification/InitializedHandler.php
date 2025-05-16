<?php

namespace KLP\KlpMcpServer\Server\Notification;

use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;

class InitializedHandler implements NotificationHandler
{
    public function isHandle(?string $method): bool
    {
        return $method === 'notifications/initialized';
    }

    public function execute(?array $params = null): array
    {
        return [];
    }
}
