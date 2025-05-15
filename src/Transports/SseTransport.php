<?php

namespace KLP\KlpMcpServer\Transports;

use Exception;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use Psr\Log\LoggerInterface;

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
final class SseTransport implements SseTransportInterface
{
    /**
     * Tracks if the server-side connection is considered active.
     */
    protected bool $connected = false;

    /**
     * Callbacks executed when the connection is closed via `close()`.
     *
     * @var array<callable>
     */
    protected array $closeHandlers = [];

    /**
     * Callbacks executed on transport errors, typically via `triggerError()`.
     *
     * @var array<callable>
     */
    protected array $errorHandlers = [];

    /**
     * Callbacks executed via `processMessage()` for adapter-mediated messages.
     *
     * @var array<callable>
     */
    protected array $messageHandlers = [];

    /**
     * Optional adapter for message persistence and retrieval (e.g., Redis).
     * Enables simulation of request/response patterns over SSE.
     */
    protected ?SseAdapterInterface $adapter = null;

    /**
     * Unique identifier for the client connection, generated during initialization.
     */
    protected ?string $clientId = null;

    /**
     * Stores the timestamp of the most recent ping, represented as an integer.
     */
    protected int $lastPingTimestamp = 0;

    /**
     * Defines the interval, in secondes, at which ping messages are sent to maintain the connection.
     */
    protected int $pingInterval = 60;

    public function __construct(
        private readonly string $defaultPath,
        ?SseAdapterInterface $adapter = null,
        private readonly ?LoggerInterface $logger = null
    ) {
        $this->adapter = $adapter;
    }

    /**
     * Starts the SSE transport connection.
     * Sets the connected flag and initializes the transport. Idempotent.
     *
     * @throws Exception If initialization fails.
     */
    public function start(): void
    {
        if ($this->connected) {
            return;
        }

        set_time_limit(0);
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', false);
        ini_set('zlib.default_socket_timeout', 5);

        $this->connected = true;
        $this->initialize();
    }

    /**
     * Initializes the transport: generates client ID and sends the initial 'endpoint' event.
     * Adapter-specific initialization might occur here or externally.
     *
     * @throws SseAdapterException If sending the initial event fails.
     */
    public function initialize(): void
    {
        if ($this->clientId === null) {
            $this->clientId = uniqid();
        }
        $this->lastPingTimestamp = time();
        $this->adapter?->storeLastPongResponseTimestamp($this->clientId, time());

        $this->sendEvent(event: 'endpoint', data: $this->getEndpoint(sessionId: $this->clientId));
    }

    /**
     * Sends a formatted SSE event to the client and flushes output buffers.
     *
     * @param  string  $event  The event name.
     * @param  string  $data  The event data payload.
     */
    private function sendEvent(string $event, string $data): void
    {
        $this->logger?->debug('SSE Transport::sendEvent: event: '.$event.PHP_EOL.'data: '.$data.PHP_EOL);

        // Just ensure output gets flushed
        flush(); // Flushes the system-level buffer (important for real-time outputs)

        echo sprintf('event: %s', $event).PHP_EOL;
        echo sprintf('data: %s', $data).PHP_EOL;
        echo PHP_EOL;

        flush(); // Ensure the data is sent to the client
    }

    /**
     * Sends a message payload as a 'message' type SSE event.
     * Encodes array messages to JSON.
     *
     * @param  string|array  $message  The message content.
     *
     * @throws Exception If JSON encoding fails or sending the event fails.
     */
    public function send(string|array $message): void
    {
        if (is_array($message)) {
            $message = json_encode(array_merge(['id' => uniqid('r')], $message));
        }

        $this->sendEvent(event: 'message', data: $message);
    }

    /**
     * Closes the connection, notifies handlers, cleans up adapter resources, and attempts a final 'close' event.
     * Idempotent. Errors during cleanup/final event are logged.
     *
     * @throws Exception From handlers if they throw exceptions.
     */
    public function close(): void
    {
        if (! $this->connected) {
            return;
        }

        $this->connected = false;

        foreach ($this->closeHandlers as $handler) {
            try {
                call_user_func($handler);
            } catch (Exception $e) {
                $this->logger?->error('Error in SSE close handler: '.$e->getMessage());
            }
        }

        if ($this->adapter !== null && $this->clientId !== null) {
            try {
                $this->adapter->removeAllMessages($this->clientId);
            } catch (SseAdapterException $e) {
                $this->logger?->error('Error cleaning up SSE adapter resources on close: '.$e->getMessage());
            }
        }

        $this->sendEvent(event: 'close', data: '{"reason":"server_closed"}');
    }

    /**
     * Registers a callback to execute when `close()` is called.
     *
     * @param  callable  $handler  The callback (takes no arguments).
     */
    public function onClose(callable $handler): void
    {
        $this->closeHandlers[] = $handler;
    }

    /**
     * Registers a callback to execute on transport errors triggered by `triggerError()`.
     *
     * @param  callable  $handler  The callback (receives string error message).
     */
    public function onError(callable $handler): void
    {
        $this->errorHandlers[] = $handler;
    }

    /**
     * Registers a callback for processing adapter-mediated messages via `processMessage()`.
     *
     * @param  callable  $handler  The callback (receives string clientId, array message).
     */
    public function onMessage(callable $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    /**
     * Checks if the connection is currently active based on the last ping timestamp
     * and the defined ping interval.
     *
     * @return bool True if the connection is active, false otherwise.
     */
    public function isConnected(): bool
    {
        if (time() - $this->lastPingTimestamp > $this->pingInterval) {
            $this->lastPingTimestamp = time();
            $this->send(message: ['jsonrpc' => '2.0', 'method' => 'ping']);
        }
        $pingTest = time() - $this->getLastPongResponseTimestamp() < $this->pingInterval + 60;
        if (! $pingTest) {
            $this->logger?->info('SSE Transport::isConnected: pingTest failed');
        }

        return $pingTest && connection_aborted() === 0;
    }

    /**
     * Receives messages for this client via the configured adapter.
     * Returns an empty array if no adapter, no messages, or on error.
     * Triggers error handlers on adapter failure.
     *
     * @return array<string|array> An array of message payloads.
     */
    public function receive(): array
    {
        if ($this->adapter !== null && $this->clientId !== null && $this->connected) {
            try {
                $messages = $this->adapter->receiveMessages($this->clientId);

                return $messages ?: [];
            } catch (SseAdapterException $e) {
                $this->triggerError('SSE Failed to receive messages via adapter: '.$e->getMessage());
            }
        } elseif ($this->adapter === null) {
            $this->logger?->info('SSE Transport::receive called but no adapter is configured.');
        }

        return [];
    }

    /**
     * Logs an error and invokes all registered error handlers.
     * Catches exceptions within error handlers themselves.
     *
     * @param  string  $message  The error message.
     */
    protected function triggerError(string $message): void
    {
        $this->logger?->error('SSE Transport error: '.$message);

        foreach ($this->errorHandlers as $handler) {
            try {
                call_user_func($handler, $message);
            } catch (Exception $e) {
                $this->logger?->error('Error in SSE error handler itself: '.$e->getMessage());
            }
        }
    }

    /**
     * Sets the adapter instance used for message persistence/retrieval.
     *
     * @param  SseAdapterInterface|null  $adapter  The adapter implementation.
     */
    public function setAdapter(?SseAdapterInterface $adapter): void
    {
        $this->adapter = $adapter;
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

    private function getEndpoint(string $sessionId): string
    {
        return sprintf('/%s/message?sessionId=%s',
            trim($this->defaultPath, '/'),
            $sessionId,
        );
    }

    /**
     * @throws SseAdapterException
     */
    protected function getLastPongResponseTimestamp(): ?int
    {
        return $this->adapter->getLastPongResponseTimestamp($this->clientId);
    }

    /**
     * Sets the interval for sending ping requests.
     *
     * @param  int  $pingInterval  The interval in milliseconds at which ping requests should be sent.
     *                             The value must be between 60 and 180 secondes.
     */
    protected function setPingInterval(int $pingInterval): void
    {
        $this->pingInterval = max(60, min($pingInterval, 180));
    }

    public function getAdapter(): ?SseAdapterInterface
    {
        return $this->adapter;
    }
}
