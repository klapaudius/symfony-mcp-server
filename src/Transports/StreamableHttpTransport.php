<?php

namespace KLP\KlpMcpServer\Transports;

use Exception;
use KLP\KlpMcpServer\Transports\Exception\StreamableHttpTransportException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Streamable HTTP Transport implementation.
 *
 * Handles bidirectional communication using HTTP streaming for server-to-client
 * and HTTP POST requests for client-to-server communication.
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/transports
 * @since 1.2.0
 */
final class StreamableHttpTransport extends AbstractTransport implements StreamableHttpTransportInterface
{
    /**
     * Initializes the class with the default path, adapter, logger, and ping settings.
     *
     * @param  RouterInterface  $router  The router instance.
     * @param  SseAdapterInterface|null  $adapter  Optional adapter for message persistence and retrieval.
     * @param  LoggerInterface|null  $logger  The logger instance (optional).
     * @param  bool  $pingEnabled  Flag to enable or disable ping functionality.
     * @param  int  $pingInterval  The interval, in seconds, at which ping messages are sent to maintain the connection.
     */
    public function __construct(
        protected readonly RouterInterface $router,
        protected ?SseAdapterInterface $adapter = null,
        protected readonly ?LoggerInterface $logger = null,
        protected bool $pingEnabled = false,
        protected int $pingInterval = 10
    ) {}

    /**
     * Checks if the connection is currently active based on the last ping timestamp
     * and the defined ping interval.
     *
     * @return bool True if the connection is active, false otherwise.
     */
    public function isConnected(): bool
    {
        $hasMessages = $this->adapter->hasMessages($this->clientId);
        $this->logger?->debug('Streamable HTTP Transport::isConnected: hasMessages: '.($hasMessages ? 'true' : 'false'));

        return $hasMessages && parent::isConnected();
    }

    public function setConnected(bool $connected): void
    {
        $this->connected = $connected;
        $this->lastPingTimestamp = time();
    }

    /**
     * Processes a message payload by invoking all registered message handlers.
     * Typically called after receiving a message from the client.
     *
     * @param  string  $clientId  The client ID associated with the message.
     * @param  array  $message  The message payload (usually an array).
     */
    public function processMessage(string $clientId, array $message): void
    {
        foreach ($this->messageHandlers as $handler) {
            try {
                $handler($clientId, $message);
            } catch (Exception $e) {
                $this->logger?->error('Error processing Streamable HTTP message via handler: '.$e->getMessage(), [
                    'clientId' => $clientId,
                ]);
            }
        }
    }

    /**
     * Pushes a message to the adapter for later retrieval by the target client.
     * Encodes the message to JSON before pushing.
     *
     * @param  string  $clientId  The target client ID.
     * @param  array  $message  The message payload (as an array).
     *
     * @throws Exception If adapter is not set, JSON encoding fails, or adapter push fails.
     */
    public function pushMessage(string $clientId, array $message): void
    {
        if ($this->adapter === null) {
            throw new StreamableHttpTransportException('Cannot push message: Adapter is not configured.');
        }

        $messageString = json_encode($message);
        if ($messageString === false) {
            throw new StreamableHttpTransportException('Failed to JSON encode message for pushing: '.json_last_error_msg());
        }
        $this->logger?->debug('Streamable HTTP Transport::pushMessage: clientId: '.$clientId." message: $messageString");

        $this->adapter->pushMessage(clientId: $clientId, message: $messageString);

        $this->receive();
        $this->send(message: $message);
    }

    /**
     * Gets the name of the transport for logging and error messages.
     *
     * @return string The transport name.
     */
    protected function getTransportName(): string
    {
        return 'Streamable HTTP Transport';
    }

    public function sendHeaders(): void
    {
        set_time_limit(0);
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', false);
        ini_set('zlib.default_socket_timeout', 5);
    }

    public function setClientSamplingCapability(bool $hasSamplingCapability): void
    {
        $this->getAdapter()->storeSamplingCapability($this->getClientId(), $hasSamplingCapability);;
    }
}
