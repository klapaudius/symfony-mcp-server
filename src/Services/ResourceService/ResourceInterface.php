<?php

namespace KLP\KlpMcpServer\Services\ResourceService;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;

interface ResourceInterface
{
    /**
     * Get the URI of the resource
     *
     * @return string The URI of the resource.
     */
    public function getUri(): string;

    /**
     * Retrieves the name.
     *
     * @return string The name.
     */
    public function getName(): string;

    /**
     * Retrieve the description of the resource
     *
     * @return string The description of the resource.
     */
    public function getDescription(): string;

    /**
     * Retrieve the MIME type of the resource
     *
     * @return string The MIME type of the resource.
     */
    public function getMimeType(): string;

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
