<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Data\Requests\InitializeData;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Server\MCPServer;
use KLP\KlpMcpServer\Transports\SseTransportInterface;

class PingHandler implements RequestHandler
{

    public function __construct(private readonly SseTransportInterface $transport)
    {
    }

    public function isHandle(string $method): bool
    {
        return $method === 'ping';
    }


    public function execute(string $method, string|int $messageId, ?array $params = null): array
    {
        $this->transport->send(["id" => $messageId, "jsonrpc" => "2.0", "result" => []]);
        return [];
    }
}
