<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Data\Requests\InitializeData;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Server\MCPServer;

class InitializeHandler implements RequestHandler
{
    private MCPServer $server;

    public function __construct(MCPServer $server)
    {
        $this->server = $server;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'initialize';
    }

    /**
     * @throws JsonRpcErrorException
     */
    public function execute(string $method, ?array $params = null): array
    {
        $data = InitializeData::fromArray(data: $params);
        $result = $this->server->initialize(data: $data);

        return $result->toArray();
    }
}
