<?php

namespace KLP\KlpMcpServer\Protocol;

use Exception;
use KLP\KlpMcpServer\Data\Resources\JsonRpc\JsonRpcResourceCollection;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;

/**
 * MCPProtocol
 *
 * @internal
 * @see https://modelcontextprotocol.io/docs/concepts/architecture
 */
interface MCPProtocolInterface
{
    public const PROTOCOL_VERSION_SSE = '2024-11-05';
    public const PROTOCOL_VERSION_STREAMABE_HTTP = '2025-03-26';

    public function setProtocolVersion(string $version): void;

    /**
     * @throws Exception
     */
    public function connect(string $version): void;

    public function send(string|array $message): void;

    public function disconnect(): void;

    public function registerRequestHandler(RequestHandler $handler): void;

    public function registerNotificationHandler(NotificationHandler $handler): void;

    public function handleMessage(string $clientId, array $message): void;

    public function requestMessage(string $clientId, array $message): void;

    public function getResponseResult(string $clientId): array;

    public function getClientId(): string;
}
