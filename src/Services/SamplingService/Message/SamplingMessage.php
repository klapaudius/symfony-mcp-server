<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService\Message;

class SamplingMessage
{
    public function __construct(
        private string $role,
        private SamplingContent $content,
    ) {
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): SamplingContent
    {
        return $this->content;
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['role'],
            SamplingContent::fromArray($data['content'])
        );
    }
}
