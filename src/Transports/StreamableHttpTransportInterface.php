<?php

namespace KLP\KlpMcpServer\Transports;

interface StreamableHttpTransportInterface extends SseTransportInterface
{
    public function setConnected(bool $connected): void;

    public function sendHeaders(): void;
}
