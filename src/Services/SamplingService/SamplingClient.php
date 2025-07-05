<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingMessage;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryException;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\TransportInterface;
use Psr\Log\LoggerInterface;

class SamplingClient implements SamplingInterface
{
    private bool $enabled = true;

    private ?string $currentClientId = null;

    private ?TransportInterface $transport = null;

    private ?ResponseWaiter $responseWaiter = null;

    private bool $handlerRegistered = false;

    public function __construct(
        private TransportFactoryInterface $transportFactory,
        private LoggerInterface $logger,
        private int $defaultTimeout = 30,
    ) {}

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
        if (! $this->enabled || $this->currentClientId === null) {
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
        ?ModelPreferences $modelPreferences = null,
        ?string $systemPrompt = null,
        ?int $maxTokens = null
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
        ?ModelPreferences $modelPreferences = null,
        ?string $systemPrompt = null,
        ?int $maxTokens = null
    ): SamplingResponse {
        if (! $this->canSample()) {
            throw new JsonRpcErrorException('Sampling is not available for the current client', JsonRpcErrorCode::METHOD_NOT_FOUND);
        }

        $request = new SamplingRequest($messages, $modelPreferences, $systemPrompt, $maxTokens);

        $this->logger->info('Creating sampling request', [
            'clientId' => $this->currentClientId,
            'messageCount' => count($messages),
        ]);

        // Generate a unique message ID for this request
        $messageId = uniqid('sampling_', true);

        // Ensure response handler is registered
        $this->ensureResponseHandler();

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

        // Wait for the response (use shorter timeout for tests)
        $timeout = $this->defaultTimeout;
        if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
            $timeout = 1; // Use 1 second timeout for tests
        }

        $responseData = $this->getResponseWaiter()->waitForResponse($messageId, $timeout);

        // Handle error response
        if ($responseData instanceof JsonRpcErrorException) {
            throw $responseData;
        }

        // Convert response data to SamplingResponse
        return $this->createSamplingResponse($responseData);
    }

    /**
     * Get or create the response waiter
     */
    private function getResponseWaiter(): ResponseWaiter
    {
        if ($this->responseWaiter === null) {
            $this->responseWaiter = new ResponseWaiter($this->logger, $this->defaultTimeout);
        }

        return $this->responseWaiter;
    }

    /**
     * Ensure the response handler is registered with the transport
     */
    private function ensureResponseHandler(): void
    {
        if ($this->handlerRegistered) {
            return;
        }

        $transport = $this->getTransport();
        $transport->onMessage([$this, 'handleIncomingMessage']);
        $this->handlerRegistered = true;

        $this->logger->debug('Registered sampling response handler');
    }

    /**
     * Handle incoming messages from the transport
     */
    public function handleIncomingMessage(string $clientId, array $message): void
    {
        // Only handle messages for our current client
        if ($clientId !== $this->currentClientId) {
            return;
        }

        // Only handle response messages (messages with an ID)
        if (!isset($message['id'])) {
            return;
        }

        // Let the response waiter handle the message
        $this->getResponseWaiter()->handleResponse($message);
    }

    /**
     * Create a SamplingResponse from response data
     */
    private function createSamplingResponse(mixed $responseData): SamplingResponse
    {
        // If the response data is null or not an array, create a simple error response
        if (!is_array($responseData)) {
            return new SamplingResponse(
                'assistant',
                new SamplingContent('text', 'Error: Invalid response format'),
                null,
                'error'
            );
        }

        // Convert the response data to a proper SamplingResponse
        try {
            return SamplingResponse::fromArray($responseData);
        } catch (\Throwable $e) {
            // If parsing fails, create an error response
            return new SamplingResponse(
                'assistant',
                new SamplingContent('text', 'Error: Failed to parse response - ' . $e->getMessage()),
                null,
                'error'
            );
        }
    }
}
