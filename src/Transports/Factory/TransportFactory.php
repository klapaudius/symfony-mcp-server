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
final class TransportFactory implements TransportFactoryInterface
{
    private ?TransportInterface $transport = null;

    private ?string $protocolVersion = null;

    /**
     * Initializes the factory with required dependencies.
     *
     * @param  RouterInterface  $router  The router instance for generating endpoints.
     * @param  SseAdapterInterface|null  $adapter  Optional adapter for message persistence and retrieval.
     * @param  LoggerInterface|null  $logger  Optional logger for transport operations.
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ?SseAdapterInterface $adapter = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $pingEnabled = false,
        private readonly int $pingInterval = 10
    ) {}

    /**
     * Creates a transport instance based on the specified protocol version.
     *
     * @param  string  $protocolVersion  The MCP protocol version to use.
     * @return TransportInterface The created transport instance.
     *
     * @throws InvalidArgumentException If the protocol version is not supported.
     */
    public function create(string $protocolVersion): TransportInterface
    {
        $this->setProtocolVersion($protocolVersion);
        // Create the appropriate transport based on the protocol version
        $this->transport = match ($this->protocolVersion) {
            MCPProtocolInterface::PROTOCOL_FIRST_VERSION => new SseTransport(
                router: $this->router,
                adapter: $this->adapter,
                logger: $this->logger,
                pingEnabled: $this->pingEnabled,
                pingInterval: $this->pingInterval
            ),
            MCPProtocolInterface::PROTOCOL_SECOND_VERSION => new StreamableHttpTransport(
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

        return $this->transport;
    }

    /**
     * Retrieves the currently initialized transport instance.
     *
     * @return TransportInterface The currently initialized transport instance.
     *
     * @throws TransportFactoryException If the transport has not been initialized yet.
     */
    public function get(): TransportInterface
    {
        if ($this->transport === null) {
            throw new TransportFactoryException('Transport must be initialized first. Please use create() method.');
        }

        return $this->transport;
    }

    /**
     * Returns the list of supported protocol versions.
     *
     * @return array<string> The supported protocol versions.
     */
    public function getSupportedVersions(): array
    {
        return [
            MCPProtocolInterface::PROTOCOL_FIRST_VERSION,
            MCPProtocolInterface::PROTOCOL_SECOND_VERSION,
        ];
    }

    private function setProtocolVersion(string $protocolVersion): void
    {
        if ($this->protocolVersion) {
            throw new InvalidArgumentException('Protocol version already set to a different value.');
        }
        $this->protocolVersion = $protocolVersion;
    }
}
