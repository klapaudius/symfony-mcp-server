<?php

namespace KLP\KlpMcpServer\Transports;

use Exception;
use KLP\KlpMcpServer\Transports\Exception\SseTransportException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * SSE (Server-Sent Events) Transport implementation.
 *
 * Handles one-way server-to-client communication using the SSE protocol.
 * Optionally uses an adapter for simulating bi-directional communication.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/transports
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events
 * @since 1.0.0
 */
final class SseTransport extends AbstractTransport implements SseTransportInterface
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
     * Initializes the transport: generates client ID and sends the initial 'endpoint' event.
     * Adapter-specific initialization might occur here or externally.
     *
     * @throws SseAdapterException If sending the initial event fails.
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->sendEvent(event: 'endpoint', data: $this->getEndpoint(sessionId: $this->getClientId()));
    }

    /**
     * Processes a message payload by invoking all registered message handlers.
     * Typically called after `receive()`. Catches exceptions within handlers.
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
                $this->logger?->error('Error processing SSE message via handler: '.$e->getMessage(), [
                    'clientId' => $clientId,
                    // Avoid logging potentially sensitive message content in production
                    // 'message_summary' => is_array($message) ? json_encode(array_keys($message)) : substr($message, 0, 100)
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
     * @throws SseTransportException If adapter is not set, JSON encoding fails, or adapter push fails.
     */
    public function pushMessage(string $clientId, array $message): void
    {
        if ($this->adapter === null) {
            throw new SseTransportException('Cannot push message: SSE Adapter is not configured.');
        }

        $messageString = json_encode($message);
        if ($messageString === false) {
            throw new SseTransportException('Failed to JSON encode message for pushing: '.json_last_error_msg());
        }

        $this->adapter->pushMessage(clientId: $clientId, message: $messageString);
    }

    protected function getEndpoint(string $sessionId): string
    {
        return $this->router->generate('message_route', ['sessionId' => $sessionId]);
    }

    /**
     * Gets the name of the transport for logging and error messages.
     *
     * @return string The transport name.
     */
    protected function getTransportName(): string
    {
        return 'SSE Transport';
    }
}
