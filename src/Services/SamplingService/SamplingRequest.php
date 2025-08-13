<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingMessage;

class SamplingRequest
{
    /**
     * @param  SamplingMessage[]  $messages
     */
    public function __construct(
        private array $messages,
        private ?ModelPreferences $modelPreferences = null,
        private ?string $systemPrompt = null,
        private ?int $maxTokens = null,
    ) {}

    /**
     * @return SamplingMessage[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getModelPreferences(): ?ModelPreferences
    {
        return $this->modelPreferences;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function toArray(): array
    {
        $result = [
            'messages' => array_map(fn (SamplingMessage $msg) => $msg->toArray(), $this->messages),
        ];

        if ($this->modelPreferences !== null) {
            $result['modelPreferences'] = $this->modelPreferences->toArray();
        }

        if ($this->systemPrompt !== null) {
            $result['systemPrompt'] = $this->systemPrompt;
        }

        if ($this->maxTokens !== null) {
            $result['maxTokens'] = $this->maxTokens;
        }

        return $result;
    }

    public static function fromArray(array $data): self
    {
        $messages = array_map(
            fn (array $msg) => SamplingMessage::fromArray($msg),
            $data['messages']
        );

        return new self(
            $messages,
            isset($data['modelPreferences']) ? ModelPreferences::fromArray($data['modelPreferences']) : null,
            $data['systemPrompt'] ?? null,
            $data['maxTokens'] ?? null
        );
    }
}
