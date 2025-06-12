<?php

namespace KLP\KlpMcpServer\Protocol\Handlers;

interface RequestHandler
{
    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array;

    public function isHandle(string $method): bool;
}
