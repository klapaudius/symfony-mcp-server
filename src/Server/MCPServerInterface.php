<?php

namespace KLP\KlpMcpServer\Server;

use KLP\KlpMcpServer\Data\Requests\InitializeData;
use KLP\KlpMcpServer\Data\Resources\InitializeResource;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;

/**
 * MCPServer
 *
 * Main server class for the Model Context Protocol (MCP) implementation.
 * This class orchestrates the server's lifecycle, including initialization,
 * handling capabilities, and routing incoming requests and notifications
 * through the configured MCPProtocol handler.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/architecture Describes the overall MCP architecture.
 */
interface MCPServerInterface
{
    /**
     * Sets the protocol version for the current communication session.
     * Can accept one of the following values:
     * - '2024-11-05'
     * - '2025-03-26'
     * - '2025-06-18'
     *
     * If the protocol version is not set, the server will use the default version for the transport type.
     *
     * @param  string  $protocolVersion  The protocol version to be set.
     */
    public function setProtocolVersion(string $protocolVersion): void;

    /**
     * Registers a request handler with the protocol layer.
     * Request handlers process incoming method calls from the client.
     *
     * @param  RequestHandler  $handler  The request handler instance to register.
     */
    public function registerRequestHandler(RequestHandler $handler): void;

    /**
     * Registers the necessary request handlers for MCP Tools functionality.
     * This typically includes handlers for 'tools/list' and 'tools/call'.
     *
     * @param  ToolRepository  $toolRepository  The repository containing available tools.
     */
    public function registerToolRepository(ToolRepository $toolRepository): self;

    /**
     * Registers the necessary request handlers for MCP Resources functionality.
     * This typically includes handlers for 'resources/list' and 'resources/read'.
     *
     * @param  ResourceRepository  $resourceRepository  The repository containing available resources.
     */
    public function registerResourceRepository(ResourceRepository $resourceRepository): self;

    /**
     * Registers the necessary request handlers for MCP Prompts functionality.
     * This typically includes handlers for 'prompts/list' and 'prompts/get'.
     *
     * @param  PromptRepository  $promptRepository  The repository containing available prompts.
     */
    public function registerPromptRepository(PromptRepository $promptRepository): self;

    /**
     * Initiates the connection process via the protocol handler.
     * Depending on the transport (e.g., SSE), this might start listening for client connections.
     */
    public function connect(): void;

    /**
     * Initiates the disconnection process via the protocol handler.
     */
    public function disconnect(): void;

    /**
     * Registers a notification handler with the protocol layer.
     * Notification handlers process incoming notifications from the client (requests without an ID).
     *
     * @param  NotificationHandler  $handler  The notification handler instance to register.
     */
    public function registerNotificationHandler(NotificationHandler $handler): void;

    /**
     * Handles the 'initialize' request from the client.
     * Stores client capabilities, checks the protocol version, and marks the server as initialized.
     * Throws an error if the server is already initialized.
     *
     * @param  InitializeData  $data  The data object containing initialization parameters from the client.
     * @return InitializeResource A resource object containing the server's initialization response.
     *
     * @throws JsonRpcErrorException If the server has already been initialized (JSON-RPC error code -32600).
     */
    public function initialize(InitializeData $data): InitializeResource;

    /**
     * Forwards a request message to a specific client via the protocol handler.
     * Used for server-initiated requests to the client (if supported by the protocol/transport).
     *
     * @param  string  $clientId  The identifier of the target client.
     * @param  array<string, mixed>  $message  The request message payload (following JSON-RPC structure).
     */
    public function requestMessage(string $clientId, array $message): void;

    public function getResponseResult(string $clientId): array;

    /**
     * Retrieves the client ID. If the client ID is not already set, generates a unique ID.
     *
     * @return string The client ID.
     */
    public function getClientId(): string;
}
