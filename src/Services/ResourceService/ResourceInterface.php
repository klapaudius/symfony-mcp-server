<?php

namespace KLP\KlpMcpServer\Services\ResourceService;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;

interface ResourceInterface extends ResourceDescriptorInterface
{
    /**
     * Get the URI of the resource
     *
     * @return string The URI of the resource.
     */
    public function getUri(): string;

    /**
     * Retrieves the data as a string.
     *
     * @return string The data retrieved.
     */
    public function getData(): string;

    /**
     * Retrieves the size.
     *
     * @return int The size value.
     */
    public function getSize(): int;
}
