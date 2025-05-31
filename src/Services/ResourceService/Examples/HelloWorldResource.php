<?php

namespace KLP\KlpMcpServer\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;

class HelloWorldResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'file:/hello-world.txt';
    }

    public function getName(): string
    {
        return 'hello-world.txt';
    }

    public function getDescription(): string
    {
        return 'The HelloWorld resource.';
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }

    public function getData(): string
    {
        return 'Hello, World!';
    }

    public function getSize(): int
    {
        return strlen($this->getData());
    }
}
