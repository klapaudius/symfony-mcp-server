<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

use KLP\KlpMcpServer\Protocol\Handlers\ResponseHandler;
use Psr\Log\LoggerInterface;

/**
 * Handles sampling response messages in the MCP protocol
 */
class SamplingResponseHandler implements ResponseHandler
{
    public function __construct(
        private SamplingClient $samplingClient,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Execute the response handler
     *
     * @param string $clientId The client ID that sent the response
     * @param string|int $messageId The message ID of the response
     * @param array|null $result The result data if successful
     * @param array|null $error The error data if failed
     */
    public function execute(string $clientId, string|int $messageId, array|null $result = null, array|null $error = null): void
    {
        // Get the response waiter from the sampling client
        $this->logger->debug('SamplingResponseHandler::execute', [
            'clientId' => $clientId,
            'messageId' => $messageId,
            'hasResult' => $result !== null,
            'hasError' => $error !== null,
        ]);

        // Construct the response message in the format expected by ResponseWaiter
        $message = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
        ];

        if ($error !== null) {
            $message['error'] = $error;
        } else {
            $message['result'] = $result;
        }

        // Let the response waiter handle the response
        try {
            $responseWaiter = $this->samplingClient->getResponseWaiter();
            $responseWaiter->handleResponse($message);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle sampling response', [
                'error' => $e->getMessage(),
                'messageId' => $messageId
            ]);
        }
    }

    /**
     * Check if this handler can handle the given message ID
     *
     * @param string|int $messageId The message ID to check
     * @return bool True if this is a sampling message ID
     */
    public function isHandle(string|int $messageId): bool
    {
        try {
            $responseWaiter = $this->samplingClient->getResponseWaiter();
            return $responseWaiter->isWaitingFor($messageId);
        } catch (\Exception $e) {
            // If we can't get the response waiter, we can't handle the message
            return false;
        }
    }
}
