<?php

namespace KLP\KlpMcpServer\Services\ResourceService;

/**
 * Represents a resource in the MCP server.
 */
class Resource implements ResourceInterface
{
    /**
     * @var string The URI of the resource.
     */
    protected string $uri;

    /**
     * @var string The name of the resource.
     */
    protected string $name;

    /**
     * @var string The description of the resource.
     */
    protected string $description;

    /**
     * @var string The MIME type of the resource.
     */
    protected string $mimeType;

    /**
     * @var string The data of the resource.
     */
    protected string $data;

    /**
     * @var int The size of the resource in bytes.
     */
    protected int $size;

    /**
     * Constructor.
     *
     * @param string $uri The URI of the resource.
     * @param string $name The name of the resource.
     * @param string $description The description of the resource.
     * @param string $mimeType The MIME type of the resource.
     * @param string $data The data of the resource.
     */
    public function __construct(string $uri, string $name, string $description, string $mimeType, string $data)
    {
        $this->uri = $uri;
        $this->name = $name;
        $this->description = $description;
        $this->mimeType = $mimeType;
        $this->data = $data;
        $this->size = strlen($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Set the name of the resource.
     *
     * @param string $name The new name of the resource.
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the description of the resource.
     *
     * @param string $description The new description of the resource.
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set the MIME type of the resource.
     *
     * @param string $mimeType The new MIME type of the resource.
     * @return self
     */
    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    /**
     * Set the data of the resource.
     *
     * @param string $data The new data of the resource.
     * @return self
     */
    public function setData(string $data): self
    {
        $this->data = $data;
        $this->size = strlen($data);
        return $this;
    }
}
