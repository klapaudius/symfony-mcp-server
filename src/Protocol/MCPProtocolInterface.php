<?php

namespace KLP\KlpMcpServer\Protocol;

use Exception;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Protocol\Handlers\ResponseHandler;

/**
 * MCPProtocol
 *
 * @internal
 *
 * @see https://modelcontextprotocol.io/docs/concepts/architecture
 */
interface MCPProtocolInterface
{
    public const PROTOCOL_FIRST_VERSION = '2024-11-05';

    public const PROTOCOL_SECOND_VERSION = '2025-03-26';

    public const PROTOCOL_THIRD_VERSION = '2025-06-18';

    /**
     * @deprecated use PROTOCOL_FIRST_VERSION instead
     */
    public const PROTOCOL_VERSION_SSE = self::PROTOCOL_FIRST_VERSION;

    /**
     * @deprecated use PROTOCOL_SECOND_VERSION instead
     */
    public const PROTOCOL_VERSION_STREAMABE_HTTP = self::PROTOCOL_SECOND_VERSION;

    /**
     * @throws \InvalidArgumentException If the protocol version is not supported.
     */
    public function setProtocolVersion(string $version): void;

    /**
     * @throws Exception
     */
    public function connect(string $version): void;

    public function send(string|array $message): void;

    public function disconnect(): void;

    public function registerRequestHandler(RequestHandler $handler): void;

    public function registerResponseHandler(ResponseHandler $handler): void;

    public function registerNotificationHandler(NotificationHandler $handler): void;

    public function handleMessage(string $clientId, array $message): void;

    public function requestMessage(string $clientId, array $message): void;

    public function getResponseResult(string $clientId): array;

    public function getClientId(): string;

    public function setClientSamplingCapability(bool $hasSamplingCapability): void;
}
