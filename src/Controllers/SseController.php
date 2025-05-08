<?php

namespace KLP\KlpMcpServer\Controllers;

use KLP\KlpMcpServer\Server\MCPServerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController
{
    public function __construct(private readonly MCPServerInterface $server) {}

    public function handle(Request $request)
    {
        return new StreamedResponse(fn () => $this->server->connect(), headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, private',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
