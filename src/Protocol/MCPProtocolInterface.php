<?php

namespace KLP\KlpMcpServer\Protocol;


use Exception;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;

/**
 * MCPProtocol
 *
 * @see https://modelcontextprotocol.io/docs/concepts/architecture
 */
interface MCPProtocolInterface
{
    /**
     * @throws Exception
     */
    public function connect(): void;

    public function send(string|array $message): void;

    public function disconnect(): void;

    public function registerRequestHandler(RequestHandler $handler): void;

    public function registerNotificationHandler(NotificationHandler $handler): void;

    public function handleMessage(string $clientId, array $message): void;

    public function requestMessage(string $clientId, array $message): void;
}
