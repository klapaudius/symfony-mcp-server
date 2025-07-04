<?php

namespace KLP\KlpMcpServer\Server;

use KLP\KlpMcpServer\Data\Requests\InitializeData;
use KLP\KlpMcpServer\Data\Resources\InitializeResource;
use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Server\Request\InitializeHandler;
use KLP\KlpMcpServer\Server\Request\PromptsGetHandler;
use KLP\KlpMcpServer\Server\Request\PromptsListHandler;
use KLP\KlpMcpServer\Server\Request\ResourcesListHandler;
use KLP\KlpMcpServer\Server\Request\ResourcesReadHandler;
use KLP\KlpMcpServer\Server\Request\ToolsCallHandler;
use KLP\KlpMcpServer\Server\Request\ToolsListHandler;
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierRepository;
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
final class MCPServer implements MCPServerInterface
{
    /**
     * The protocol handler instance responsible for communication logic.
     */
    private MCPProtocolInterface $protocol;

    /**
     * Information about the server, typically including name and version.
     *
     * @var array{name: string, version: string}
     */
    private array $serverInfo;

    /**
     * The capabilities supported by this server instance.
     */
    private ServerCapabilitiesInterface $capabilities;

    /**
     * Flag indicating whether the server has been initialized by a client.
     */
    private bool $initialized = false;

    private ?string $protocolVersion = null;

    private ProgressNotifierRepository $progressNotifierRepository;

    private ?SamplingClient $samplingClient;

    /**
     * Creates a new MCPServer instance.
     *
     * Initializes the server with the communication protocol, server information,
     * and capabilities. Registers the mandatory 'initialize' request handler.
     *
     * @param  MCPProtocolInterface  $protocol  The protocol handler instance (e.g., for JSON-RPC over SSE).
     * @param  array{name: string, version: string}  $serverInfo  Associative array containing the server's name and version.
     * @param  ServerCapabilities|null  $capabilities  Optional server capabilities configuration. If null, default capabilities are used.
     */
    public function __construct(
        MCPProtocolInterface $protocol,
        ProgressNotifierRepository $progressNotifierRepository,
        array $serverInfo,
        ?SamplingClient $samplingClient = null,
        ?ServerCapabilitiesInterface $capabilities = null)
    {
        $this->protocol = $protocol;
        $this->progressNotifierRepository = $progressNotifierRepository;
        $this->serverInfo = $serverInfo;
        $this->capabilities = $capabilities ?? new ServerCapabilities;
        $this->samplingClient = $samplingClient;

        // Register the handler for the mandatory 'initialize' method.
        $this->registerRequestHandler(new InitializeHandler($this));
    }

    /**
     * Registers a request handler with the protocol layer.
     * Request handlers process incoming method calls from the client.
     *
     * @param  RequestHandler  $handler  The request handler instance to register.
     */
    public function registerRequestHandler(RequestHandler $handler): void
    {
        $this->protocol->registerRequestHandler($handler);
    }

    /**
     * Static factory method to create a new MCPServer instance with simplified parameters.
     *
     * @param  MCPProtocolInterface  $protocol  The protocol handler instance.
     * @param  string  $name  The server name.
     * @param  string  $version  The server version.
     * @param  ServerCapabilities|null  $capabilities  Optional server capabilities configuration.
     * @return self A new MCPServer instance.
     */
    public static function create(
        MCPProtocolInterface $protocol,
        ProgressNotifierRepository $progressNotifierRepository,
        string $name,
        string $version,
        ?ServerCapabilitiesInterface $capabilities = null
    ): self {
        return new self($protocol,
            $progressNotifierRepository, [
                'name' => $name,
                'version' => $version,
            ], null, $capabilities);
    }

    /**
     * Registers the necessary request handlers for MCP Tools functionality.
     * This typically includes handlers for 'tools/list' and 'tools/call'.
     *
     * @param  ToolRepository  $toolRepository  The repository containing available tools.
     * @return self The current MCPServer instance for method chaining.
     */
    public function registerToolRepository(ToolRepository $toolRepository): self
    {
        $this->registerRequestHandler(new ToolsListHandler($toolRepository));
        $this->registerRequestHandler(new ToolsCallHandler($toolRepository, $this->progressNotifierRepository, $this->samplingClient));
        $this->capabilities->withTools($toolRepository->getToolSchemas());

        return $this;
    }

    public function registerResourceRepository(ResourceRepository $resourceRepository): self
    {
        $this->registerRequestHandler(new ResourcesListHandler($resourceRepository));
        $this->registerRequestHandler(new ResourcesReadHandler($resourceRepository, $this->samplingClient));
        $this->capabilities->withResources($resourceRepository->getResourceSchemas());

        return $this;
    }

    /**
     * Registers the necessary request handlers for MCP Prompts functionality.
     * This includes handlers for 'prompts/list' and 'prompts/get'.
     *
     * @param  PromptRepository  $promptRepository  The repository containing available prompts.
     * @return self The current MCPServer instance for method chaining.
     */
    public function registerPromptRepository(PromptRepository $promptRepository): self
    {
        $this->registerRequestHandler(new PromptsListHandler($promptRepository));
        $this->registerRequestHandler(new PromptsGetHandler($promptRepository, $this->samplingClient));
        $this->capabilities->withPrompts($promptRepository->getPromptSchemas());

        return $this;
    }

    /**
     * Initiates the connection process via the protocol handler.
     * Depending on the transport (e.g., SSE), this might start listening for client connections.
     */
    public function connect(): void
    {
        $this->protocol->connect($this->protocolVersion ?? MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);
    }

    /**
     * Initiates the disconnection process via the protocol handler.
     */
    public function disconnect(): void
    {
        $this->protocol->disconnect();
    }

    /**
     * Registers a notification handler with the protocol layer.
     * Notification handlers process incoming notifications from the client (requests without an ID).
     *
     * @param  NotificationHandler  $handler  The notification handler instance to register.
     */
    public function registerNotificationHandler(NotificationHandler $handler): void
    {
        $this->protocol->registerNotificationHandler($handler);
    }

    /**
     * Handles the 'initialize' request from the client.
     * Stores client capabilities, checks protocol version, and marks the server as initialized.
     * Throws an error if the server is already initialized.
     *
     * @param  InitializeData  $data  The data object containing initialization parameters from the client.
     * @return InitializeResource A resource object containing the server's initialization response.
     *
     * @throws JsonRpcErrorException If the server has already been initialized (JSON-RPC error code -32600).
     */
    public function initialize(InitializeData $data): InitializeResource
    {
        if ($this->initialized) {
            throw new JsonRpcErrorException(message: 'Server already initialized', code: JsonRpcErrorCode::INVALID_REQUEST, data: $data);
        }

        $this->initialized = true;

        // Validate and determine the protocol version to use
        $requestedProtocolVersion = $data->protocolVersion ?? MCPProtocolInterface::PROTOCOL_VERSION_SSE;
        $protocolVersion = $this->getValidatedProtocolVersion($requestedProtocolVersion);

        $hasSamplingCapability = isset($data->capabilities['sampling']);
        $this->protocol->setClientSamplingCapability($hasSamplingCapability);

        return new InitializeResource(
            $this->serverInfo['name'],
            $this->serverInfo['version'],
            $this->capabilities->toInitializeMessage(),
            $protocolVersion
        );
    }

    /**
     * Validates the requested protocol version and returns a supported version.
     *
     * @param  string  $requestedVersion  The protocol version requested by the client
     * @return string A supported protocol version
     *
     * @throws JsonRpcErrorException If the requested protocol version is not supported
     */
    private function getValidatedProtocolVersion(string $requestedVersion): string
    {
        $supportedVersions = [
            MCPProtocolInterface::PROTOCOL_VERSION_SSE,
            MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP,
        ];

        // If the requested version is supported, return it
        if (in_array($requestedVersion, $supportedVersions, true)) {
            return $requestedVersion;
        }

        // return latest version
        return MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP;
    }

    /**
     * Forwards a request message to a specific client via the protocol handler.
     * Used for server-initiated requests to the client (if supported by the protocol/transport).
     *
     * @param  string  $clientId  The identifier of the target client.
     * @param  array<string, mixed>  $message  The request message payload (following JSON-RPC structure).
     */
    public function requestMessage(string $clientId, array $message): void
    {
        $this->protocol->requestMessage(clientId: $clientId, message: $message);
    }

    public function getResponseResult(string $clientId): array
    {
        return $this->protocol->getResponseResult($clientId);
    }

    /**
     * Retrieves the client ID. If the client ID is not already set, generates a unique ID.
     *
     * @return string The client ID.
     */
    public function getClientId(): string
    {
        return $this->protocol->getClientId();
    }

    public function setProtocolVersion(string $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
        $this->protocol->setProtocolVersion($protocolVersion);
    }
}
