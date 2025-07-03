<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService\Message;

class SamplingContent
{
    public function __construct(
        private string $type,
        private string|null $text = null,
        private array|null $data = null,
        private string|null $mimeType = null,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string|null
    {
        return $this->text;
    }

    public function getData(): array|null
    {
        return $this->data;
    }

    public function getMimeType(): string|null
    {
        return $this->mimeType;
    }

    public function toArray(): array
    {
        $result = ['type' => $this->type];

        if ($this->text !== null) {
            $result['text'] = $this->text;
        }

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if ($this->mimeType !== null) {
            $result['mimeType'] = $this->mimeType;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['type'],
            $data['text'] ?? null,
            $data['data'] ?? null,
            $data['mimeType'] ?? null
        );
    }
}
