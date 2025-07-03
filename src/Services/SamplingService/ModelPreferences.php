<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

class ModelPreferences
{
    public function __construct(
        private array $hints = [],
        private float|null $costPriority = null,
        private float|null $speedPriority = null,
        private float|null $intelligencePriority = null,
    ) {
    }

    public function getHints(): array
    {
        return $this->hints;
    }

    public function getCostPriority(): float|null
    {
        return $this->costPriority;
    }

    public function getSpeedPriority(): float|null
    {
        return $this->speedPriority;
    }

    public function getIntelligencePriority(): float|null
    {
        return $this->intelligencePriority;
    }

    public function toArray(): array
    {
        $result = [];

        if (!empty($this->hints)) {
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
