<?php

namespace KLP\KlpMcpServer\Controllers;

use KLP\KlpMcpServer\Server\MCPServer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MessageController
{
    public function __construct(private readonly MCPServer $server, private readonly LoggerInterface $logger) {}

    public function handle(Request $request)
    {
        $sessionId = $request->request->get('sessionId') ?? $request->query->get('sessionId');

        $messageJson = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->logger?->debug('Received message for clientId:'.$sessionId, ['message' => $messageJson]);

        $this->server->requestMessage(clientId: $sessionId, message: $messageJson);

        return new JsonResponse(['success' => true]);
    }
}
