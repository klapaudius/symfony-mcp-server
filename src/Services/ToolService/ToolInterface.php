<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;

interface ToolInterface
{
    /**
     * Retrieves the name.
     *
     * @return string The name.
     */
    public function getName(): string;

    public function getDescription(): string;

    public function getInputSchema(): array;

    public function getAnnotations(): ToolAnnotation;

    public function execute(array $arguments): mixed;
}
