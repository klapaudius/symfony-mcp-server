<?php

namespace KLP\KlpMcpServer\Transports;

use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;

interface SseTransportInterface extends TransportInterface
{
    public function getAdapter(): ?SseAdapterInterface;
}
