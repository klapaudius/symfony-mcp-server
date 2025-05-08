<?php

namespace KLP\KlpMcpServer\Transports\SseAdapters;

/**
 * In Memory Adapter for SSE Transport
 *
 * Implements the SSE Adapter interface without backend storage.
 * This adapter must not be used in a production environment.
 * It can be useful for testing tho.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/transports
 */
class InMemoryAdapter implements SseAdapterInterface
{
    private array $messages = [];

    /**
     * {@inheritDoc}
     */
    public function pushMessage(string $clientId, string $message): void
    {
        $this->messages[$clientId][] = $message;
    }

    /**
     * {@inheritDoc}
     */
    public function removeAllMessages(string $clientId): void
    {
        unset($this->messages[$clientId]);
    }

    /**
     * {@inheritDoc}
     */
    public function receiveMessages(string $clientId): array
    {
        return $this->messages[$clientId] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function popMessage(string $clientId): ?string
    {
        return array_shift($this->messages[$clientId]) ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function hasMessages(string $clientId): bool
    {
        return isset($this->messages[$clientId]) && count($this->messages[$clientId]) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessageCount(string $clientId): int
    {
        return count($this->messages[$clientId]) ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(array $config): void
    {
        // Nothing to do here for now
    }
}
