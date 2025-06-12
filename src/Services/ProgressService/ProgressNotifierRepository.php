<?php

namespace KLP\KlpMcpServer\Services\ProgressService;

use KLP\KlpMcpServer\Transports\Factory\TransportFactoryException;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\TransportInterface;

class ProgressNotifierRepository
{
    private ?TransportInterface $transport = null;

    public function __construct(
        private readonly TransportFactoryInterface $transportFactory
    ) {
    }

    /**
     * Active progress tokens to track current operations
     * @var array<string|int, array{clientId: string, lastProgress: float|int}>
     */
    private array $activeTokens = [];


    /**
     * Registers a progress token for tracking.
     * Must be called before sending progress notifications.
     *
     * @param string|int $progressToken The progress token from the request
     * @param string $clientId The client ID associated with the token
     * @throws TransportFactoryException If the transport has not been initialized yet.
     */
    public function registerToken(string|int $progressToken, string $clientId): ?ProgressNotifier
    {
        if (null === $this->transport) {
            $this->transport = $this->transportFactory->get();
        }
        $progressNotifier = null;
        if (!isset($this->activeTokens[$progressToken])) {
            $this->activeTokens[$progressToken] = [
                'clientId' => $clientId,
                'progressNotifier' => $progressNotifier = new ProgressNotifier($progressToken, [$this, 'handleMessage']),
            ];
        }

        return $progressNotifier;
    }

    /**
     * Unregisters a progress token when operation completes.
     *
     * @param string|int|null $progressToken The progress token to unregister
     */
    public function unregisterToken(string|int|null $progressToken): void
    {
        if ($progressToken === null) {
            return;
        }
        unset($this->activeTokens[$progressToken]);
    }

    /**
     * Checks if a progress token is currently active.
     *
     * @param string|int $progressToken The progress token to check
     * @return bool True if the token is active, false otherwise
     */
    public function isTokenActive(string|int $progressToken): bool
    {
        return isset($this->activeTokens[$progressToken]);
    }

    /**
     * Gets all active progress tokens.
     *
     * @return array<string|int> Array of active progress tokens
     */
    public function getActiveTokens(): array
    {
        return array_keys($this->activeTokens);
    }

    public function handleMessage(array $message): void
    {
        if (!$this->isTokenActive($message['params']['progressToken'])) {
            throw new ProgressTokenException("Invalid progress token: {$message['params']['progressToken']} is not active");
        }
        $clientId = $this->activeTokens[$message['params']['progressToken']]['clientId'];

        $this->transport->pushMessage(clientId: $clientId, message: $message);
    }
}
