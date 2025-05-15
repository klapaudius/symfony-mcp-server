<?php

namespace KLP\KlpMcpServer\Server\Notification;

use Exception;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;

/**
 * Handles the processing of "Pong" notifications for managing client-server interactions.
 * This class specifically implements the NotificationHandler interface.
 *
 * @see https://modelcontextprotocol.io/specification/2024-11-05/basic/utilities/ping
 */
readonly class PongHandler implements NotificationHandler
{
    public function __construct(private ?SseAdapterInterface $adapter = null) {}

    public function isHandle(?string $method): bool
    {
        return is_null($method);
    }

    /**
     * Executes a specified method with optional parameters.
     *
     * @param  array|null  $params  Optional parameters to pass to the method. Null if not provided.
     * @return array The result of the execution, returned as an array.
     */
    public function execute(?array $params = null): array
    {
        try {
            $this->adapter?->storeLastPongResponseTimestamp($params['clientId'], time());
        } catch (SseAdapterException) {
            // Nothing to do here the client will be disconnected anyway
        }

        return [];
    }
}
