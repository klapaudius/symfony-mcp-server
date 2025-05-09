<?php

namespace KLP\KlpMcpServer\Services\ToolService;

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

    public function getAnnotations(): array;

    public function execute(array $arguments): mixed;
}
