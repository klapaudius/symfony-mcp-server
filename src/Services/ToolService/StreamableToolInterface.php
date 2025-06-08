<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;

interface StreamableToolInterface
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

    /**
     * Determines if this tool should return a streaming response.
     *
     * When this method returns true, the execute() method should return
     * a callback function that will be used as the StreamedResponse callback.
     *
     * @return bool True if the tool supports streaming, false otherwise.
     */
    public function isStreaming(): bool;
}
