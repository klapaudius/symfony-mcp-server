<?php

namespace KLP\KlpMcpServer\Transports;

use Exception;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractTransport implements TransportInterface
{
    /**
     * Unique identifier for the client connection, generated during initialization.
     */
    protected ?string $clientId = null;

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
     * Callbacks executed via `processMessage()` for incoming messages.
     *
     * @var array<callable>
     */
    protected array $messageHandlers = [];

    /**
     * Stores the timestamp of the most recent ping, represented as an integer.
     */
    protected int $lastPingTimestamp = 0;

    /**
     * The adapter instance used for message persistence/retrieval.
     */
    protected ?SseAdapterInterface $adapter = null;

    /**
     * The router instance used for generating endpoints.
     */
    protected readonly RouterInterface $router;

    /**
     * The logger instance used for logging.
     */
    protected readonly ?LoggerInterface $logger;

    /**
     * Flag to enable or disable ping functionality.
     */
    protected bool $pingEnabled = false;

    /**
     * The interval, in seconds, at which ping messages are sent to maintain the connection.
     */
    protected int $pingInterval = 10;

    /**
     * Retrieves the client ID. If the client ID is not already set, generates a unique ID.
     *
     * @return string The client ID.
     */
    public function getClientId(): string
    {
        if ($this->clientId === null) {
            $this->clientId = uniqid();
        }

        return $this->clientId;
    }

    /**
     * Starts the transport connection.
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
     * Sends a message payload as a 'message' type event.
     * Encodes array messages to JSON.
     *
     * @param  string|array  $message  The message content.
     *
     * @throws Exception If JSON encoding fails or sending the event fails.
     */
    public function send(string|array $message): void
    {
        if (is_array($message)) {
            // Check if this is a notification (has method but no id)
            $isNotification = isset($message['method']) && ! isset($message['id']);

            // Only add ID to non-notification messages
            if (! $isNotification) {
                $message = array_merge(['id' => uniqid('r')], $message);
            }

            $message = json_encode($message);
        }

        $this->sendEvent(event: 'message', data: $message);
    }

    /**
     * Sends a formatted event to the client and flushes output buffers.
     *
     * @param  string  $event  The event name.
     * @param  string  $data  The event data payload.
     */
    protected function sendEvent(string $event, string $data): void
    {
        $this->logger?->debug($this->getTransportName().'::sendEvent: event: '.$event.PHP_EOL.'data: '.$data.PHP_EOL);

        // Just ensure output gets flushed
        flush(); // Flushes the system-level buffer (important for real-time outputs)

        echo sprintf('event: %s', $event).PHP_EOL;
        echo sprintf('data: %s', $data).PHP_EOL;
        echo PHP_EOL;

        // Ensure the output buffer is flushed
        if (false !== ob_get_length()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Initializes the transport: generates client ID and sends the initial 'endpoint' event.
     */
    public function initialize(): void
    {
        $this->lastPingTimestamp = time();
        $this->adapter?->storeLastPongResponseTimestamp($this->getClientId(), time());
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
                $this->logger?->error($this->getTransportName().'. Error in close handler: '.$e->getMessage());
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
     * Registers a callback for processing incoming messages via `processMessage()`.
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
        $pingTest = true;
        if ($this->pingEnabled) {
            $pingTest = $this->checkPing();
            if (! $pingTest) {
                $this->logger?->info($this->getTransportName().'::checkPing: pingTest failed');
            }
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
            } catch (Exception $e) {
                $this->triggerError($this->getTransportName().' Failed to receive messages via adapter: '.$e->getMessage());
            }
        } elseif ($this->adapter === null) {
            $this->logger?->info($this->getTransportName().'::receive called but no adapter is configured.');
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
        $this->logger?->error($this->getTransportName().' error: '.$message);

        foreach ($this->errorHandlers as $handler) {
            try {
                call_user_func($handler, $message);
            } catch (Exception $e) {
                $this->logger?->error('Error in '.$this->getTransportName().' error handler itself: '.$e->getMessage());
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
     * Gets the adapter instance used for message persistence/retrieval.
     *
     * @return SseAdapterInterface|null The adapter implementation.
     */
    public function getAdapter(): ?SseAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Gets the last pong response timestamp from the adapter.
     *
     * @return int|null The timestamp of the last pong response, or null if no pong has been received.
     *
     * @throws Exception If the adapter fails to retrieve the timestamp.
     */
    protected function getLastPongResponseTimestamp(): ?int
    {
        return $this->adapter?->getLastPongResponseTimestamp($this->clientId);
    }

    /**
     * Sets the interval for sending ping requests.
     *
     * @param  int  $pingInterval  The interval in seconds at which ping requests should be sent.
     *                             The value must be between 5 and 30 seconds.
     */
    protected function setPingInterval(int $pingInterval): void
    {
        $this->pingInterval = max(5, min($pingInterval, 30));
    }

    /**
     * Checks if the connection is still active by sending a ping message if needed
     * and verifying the last pong response timestamp.
     *
     * @return bool True if the connection is active, false otherwise.
     *
     * @throws Exception If sending the ping message fails.
     */
    protected function checkPing(): bool
    {
        if (time() - $this->lastPingTimestamp > $this->pingInterval) {
            $this->lastPingTimestamp = time();
            try {
                $this->send(message: ['jsonrpc' => '2.0', 'method' => 'ping']);
            } catch (Exception) {
                return false;
            }
        }

        $lastPongTimestamp = $this->getLastPongResponseTimestamp() ?? time();

        return time() - $lastPongTimestamp < $this->pingInterval * 1.8;
    }

    /**
     * Gets the name of the transport for logging and error messages.
     *
     * @return string The transport name.
     */
    abstract protected function getTransportName(): string;

    abstract public function setClientSamplingCapability(bool $hasSamplingCapability): void;
}
