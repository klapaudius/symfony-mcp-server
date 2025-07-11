<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;

class SamplingResponse
{
    public function __construct(
        private string $role,
        private SamplingContent $content,
        private ?string $model = null,
        private ?string $stopReason = null,
    ) {}

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): SamplingContent
    {
        return $this->content;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getStopReason(): ?string
    {
        return $this->stopReason;
    }

    public function toArray(): array
    {
        $result = [
            'role' => $this->role,
            'content' => $this->content->toArray(),
        ];

        if ($this->model !== null) {
            $result['model'] = $this->model;
        }

        if ($this->stopReason !== null) {
            $result['stopReason'] = $this->stopReason;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        if (! isset($data['role'])) {
            throw new \InvalidArgumentException('Missing required field: role');
        }

        if (! isset($data['content'])) {
            throw new \InvalidArgumentException('Missing required field: content');
        }

        return new self(
            $data['role'],
            SamplingContent::fromArray($data['content']),
            $data['model'] ?? null,
            $data['stopReason'] ?? null
        );
    }
}
