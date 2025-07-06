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
    private ResponseWaiter $responseWaiter;

    public function __construct(
        private SamplingClient $samplingClient,
        private LoggerInterface $logger
    ) {
        $this->responseWaiter = $this->samplingClient->getResponseWaiter();
    }

    /**
     * Execute the response handler
     *
     * @param string $clientId The client ID that sent the response
     * @param string|int $messageId The message ID of the response
     * @param array|null $result The result data if successful
     * @param array|null $error The error data if failed
     * @return array Empty array as responses don't need a return
     */
    public function execute(string $clientId, string|int $messageId, array|null $result = null, array|null $error = null): array
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
        $this->responseWaiter->handleResponse($message);

        return [];
    }

    /**
     * Check if this handler can handle the given message ID
     *
     * @param string|int $messageId The message ID to check
     * @return bool True if this is a sampling message ID
     */
    public function isHandle(string|int $messageId): bool
    {
        return $this->responseWaiter->isWaitingFor($messageId);
    }
}
