<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

class ModelPreferences
{
    public function __construct(
        private array $hints = [],
        private ?float $costPriority = null,
        private ?float $speedPriority = null,
        private ?float $intelligencePriority = null,
    ) {}

    public function getHints(): array
    {
        return $this->hints;
    }

    public function getCostPriority(): ?float
    {
        return $this->costPriority;
    }

    public function getSpeedPriority(): ?float
    {
        return $this->speedPriority;
    }

    public function getIntelligencePriority(): ?float
    {
        return $this->intelligencePriority;
    }

    public function toArray(): array
    {
        $result = [];

        if (! empty($this->hints)) {
            $result['hints'] = $this->hints;
        }

        if ($this->costPriority !== null) {
            $result['costPriority'] = $this->costPriority;
        }

        if ($this->speedPriority !== null) {
            $result['speedPriority'] = $this->speedPriority;
        }

        if ($this->intelligencePriority !== null) {
            $result['intelligencePriority'] = $this->intelligencePriority;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['hints'] ?? [],
            $data['costPriority'] ?? null,
            $data['speedPriority'] ?? null,
            $data['intelligencePriority'] ?? null
        );
    }
}
