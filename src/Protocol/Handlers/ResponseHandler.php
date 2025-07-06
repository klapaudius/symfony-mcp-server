<?php

namespace KLP\KlpMcpServer\Protocol\Handlers;

interface ResponseHandler
{
    public function execute(string $clientId, string|int $messageId, ?array $result = null, ?array $error = null): array;

    public function isHandle(string|int $messageId): bool;
}
