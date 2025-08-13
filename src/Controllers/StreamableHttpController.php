<?php

namespace KLP\KlpMcpServer\Controllers;

use JsonException;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Server\MCPServerInterface;
use KLP\KlpMcpServer\Transports\Exception\StreamableHttpTransportException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class StreamableHttpController
{
    public function __construct(
        private MCPServerInterface $server,
        private ?LoggerInterface $logger = null
    ) {}

    public function handle(Request $request)
    {
        return $request->getMethod() == Request::METHOD_GET
            ? $this->gethandle()
            : $this->postHandle($request);
    }

    public function gethandle(): JsonResponse
    {
        return new JsonResponse(
            [
                'jsonrpc' => 2.0,
                'error' => 'This endpoint does not support GET requests yet.',
            ],
            Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function postHandle(Request $request): StreamedResponse|JsonResponse
    {
        $this->server->setProtocolVersion(MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);

        $clientId =
            $request->headers->get('mcp-session-id') ??
            $this->server->getClientId();

        try {
            $input = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
            $this->logger?->debug('Received message from clientId:'.$clientId, ['message' => $input]);

            $willStream = ! isset($input['jsonrpc']) && isset($input[0]['jsonrpc']);
            $messages = $willStream ? $input : [$input];

            return new StreamedResponse(function () use ($clientId, $messages) {
                foreach ($messages as $message) {
                    $this->server->requestMessage(clientId: $clientId, message: $message);
                }
            }, headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, private',
                'X-Accel-Buffering' => 'no',
                'Mcp-Session-Id' => $clientId,
            ]);
        } catch (JsonException|StreamableHttpTransportException $e) {
            $message = $e instanceof JsonException ? 'Parse error' : $e->getMessage();

            return new JsonResponse(['jsonrpc' => 2.0, 'error' => ['code' => -32700, 'message' => $message]], 400);
        }
    }
}
