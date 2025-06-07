<?php

namespace KLP\KlpMcpServer\Transports\Factory;

use InvalidArgumentException;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use KLP\KlpMcpServer\Transports\SseTransport;
use KLP\KlpMcpServer\Transports\StreamableHttpTransport;
use KLP\KlpMcpServer\Transports\TransportInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Factory for creating transport instances based on the protocol version.
 *
 * @since 1.2.0
 */
readonly class TransportFactory implements TransportFactoryInterface
{
    /**
     * Initializes the factory with required dependencies.
     *
     * @param RouterInterface $router The router instance for generating endpoints.
     * @param SseAdapterInterface|null $adapter Optional adapter for message persistence and retrieval.
     * @param LoggerInterface|null $logger Optional logger for transport operations.
     */
    public function __construct(
        private RouterInterface      $router,
        private ?SseAdapterInterface $adapter = null,
        private ?LoggerInterface     $logger = null,
        private bool                 $pingEnabled = false,
        private int                  $pingInterval = 10
    ) {}

    /**
     * Creates a transport instance based on the specified protocol version.
     *
     * @param string $protocolVersion The MCP protocol version to use.
     *
     * @return TransportInterface The created transport instance.
     *
     * @throws InvalidArgumentException If the protocol version is not supported.
     */
    public function create(string $protocolVersion): TransportInterface
    {
        // Create the appropriate transport based on the protocol version
        return match ($protocolVersion) {
            MCPProtocolInterface::PROTOCOL_VERSION_SSE => new SseTransport(
                router: $this->router,
                adapter: $this->adapter,
                logger: $this->logger,
                pingEnabled: $this->pingEnabled,
                pingInterval: $this->pingInterval
            ),
            MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP => new StreamableHttpTransport(
                router: $this->router,
                adapter: $this->adapter,
                logger: $this->logger,
                pingEnabled: $this->pingEnabled,
                pingInterval: $this->pingInterval
            ),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported protocol version: %s. Supported versions: %s',
                    $protocolVersion,
                    implode(', ', $this->getSupportedVersions())
                )
            ),
        };
    }

    /**
     * Returns the list of supported protocol versions.
     *
     * @return array<string> The supported protocol versions.
     */
    public function getSupportedVersions(): array
    {
        return [
            MCPProtocolInterface::PROTOCOL_VERSION_SSE,
            MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP
        ];
    }
}
