<?php

namespace KLP\KlpMcpServer\Services\ResourceService;

interface ResourceDescriptorInterface
{
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
}
