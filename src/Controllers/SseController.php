<?php

namespace KLP\KlpMcpServer\Controllers;

use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Server\MCPServerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class SseController
{
    public function __construct(private MCPServerInterface $server) {}

    public function handle(): StreamedResponse
    {
        $this->server->setProtocolVersion(MCPProtocolInterface::PROTOCOL_VERSION_SSE);

        return new StreamedResponse(fn () => $this->server->connect(), headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, private',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
