<?php

namespace KLP\KlpMcpServer\Services\ToolService\Annotation;

class ToolAnnotation
{
    /**
     * @var string  A human-readable title for the tool, useful for UI display
     */
    private string $title;

    /**
     * @var bool If true, indicates the tool does not modify its environment
     */
    private bool $readOnlyHint;

    /**
     * @var bool If true, the tool may perform destructive updates (only meaningful when readOnlyHint is false)
     */
    private bool $destructiveHint;

    /**
     * @var bool If true, calling the tool repeatedly with the same arguments has no additional effect (only meaningful when readOnlyHint is false);
     */
    private bool $idempotentHint;

    /**
     * @var bool If true, the tool may interact with an “open world” of external entities;
     */
    private bool $openWorldHint;

    public function __construct(
        ?string $title = null,
        ?bool $readOnlyHint = null,
        ?bool $destructiveHint = null,
        ?bool $idempotentHint = null,
        ?bool $openWorldHint = null
    )
    {
        $this->title = $title ?? '-';
        $this->readOnlyHint = $readOnlyHint ?? false;
        $this->destructiveHint = $destructiveHint ?? true;
        $this->idempotentHint = $idempotentHint ?? false;
        $this->openWorldHint = $openWorldHint ?? true;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isReadOnlyHint(): bool
    {
        return $this->readOnlyHint;
    }

    public function setReadOnlyHint(bool $readOnlyHint): void
    {
        $this->readOnlyHint = $readOnlyHint;
    }

    public function isDestructiveHint(): bool
    {
        return $this->destructiveHint;
    }

    public function isIdempotentHint(): bool
    {
        return $this->idempotentHint;
    }

    public function isOpenWorldHint(): bool
    {
        return $this->openWorldHint;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'readOnlyHint' => $this->isReadOnlyHint(),
            'destructiveHint' => $this->isDestructiveHint(),
            'idempotentHint' => $this->isIdempotentHint(),
            'openWorldHint' => $this->isOpenWorldHint(),
        ];
    }
}
