<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

use KLP\KlpMcpServer\Transports\TransportInterface;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryException;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingMessage;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use Psr\Log\LoggerInterface;

class SamplingClient implements SamplingInterface
{
    private bool $enabled = true;
    private string|null $currentClientId = null;

    private ?TransportInterface $transport = null;

    public function __construct(
        private TransportFactoryInterface $transportFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function setCurrentClientId(string $clientId): void
    {
        $this->currentClientId = $clientId;
    }

    public function canSample(): bool
    {
        if (!$this->enabled || $this->currentClientId === null) {
            return false;
        }

        $transport = $this->getTransport();
        $adapter = $transport->getAdapter();
        if ($adapter === null) {
            return false;
        }

        return $adapter->hasSamplingCapability($this->currentClientId);
    }

    /**
     * Create a text sampling request
     */
    public function createTextRequest(
        string $prompt,
        ModelPreferences|null $modelPreferences = null,
        string|null $systemPrompt = null,
        int|null $maxTokens = null
    ): SamplingResponse {
        $message = new SamplingMessage(
            'user',
            new SamplingContent('text', $prompt)
        );

        return $this->createRequest([$message], $modelPreferences, $systemPrompt, $maxTokens);
    }

    /**
     * Get or create transport instance
     */
    private function getTransport(): TransportInterface
    {
        if ($this->transport === null) {
            try {
                $this->transport = $this->transportFactory->get();
            } catch (TransportFactoryException $e) {
                // If factory hasn't been initialized, create with default protocol
                $this->transport = $this->transportFactory->create(MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);
            }
        }

        return $this->transport;
    }

    /**
     * Create a sampling request with multiple messages
     */
    public function createRequest(
        array $messages,
        ModelPreferences|null $modelPreferences = null,
        string|null $systemPrompt = null,
        int|null $maxTokens = null
    ): SamplingResponse {
        if (!$this->canSample()) {
            throw new JsonRpcErrorException('Sampling is not available for the current client', JsonRpcErrorCode::METHOD_NOT_FOUND);
        }

        $request = new SamplingRequest($messages, $modelPreferences, $systemPrompt, $maxTokens);

        $this->logger->info('Creating sampling request', [
            'clientId' => $this->currentClientId,
            'messageCount' => count($messages),
        ]);

        // Generate a unique message ID for this request
        $messageId = uniqid('sampling_', true);

        // Send the sampling request to the client
        $response = $this->sendSamplingRequest($messageId, $request);

        return $response;
    }

    private function sendSamplingRequest(string $messageId, SamplingRequest $request): SamplingResponse
    {
        $message = [
            'jsonrpc' => '2.0',
            'id' => $messageId,
            'method' => 'sampling/createMessage',
            'params' => $request->toArray(),
        ];

        // Send the request to the client
        $this->getTransport()->pushMessage($this->currentClientId, $message);

        // In a real implementation, we would need to wait for the response
        // This is a simplified version - in practice, you'd need proper async handling
        // or a response waiting mechanism

        // For now, throw an exception indicating this needs implementation
        throw new JsonRpcErrorException('Sampling response handling not yet implemented - requires async message handling', JsonRpcErrorCode::INTERNAL_ERROR);
    }
}
