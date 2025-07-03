<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

interface SamplingInterface
{
    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): void;

    public function canSample(): bool;
}
