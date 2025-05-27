<?php

namespace KLP\KlpMcpServer\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class HelloWorldResource implements ResourceInterface
{
    public function getUri(): string
    {
        return "file:/hello-world.txt";
    }

    public function getName(): string
    {
        return "hello-world.txt";
    }

    public function getDescription(): string
    {
        return "The HelloWorld resource.";
    }

    public function getMimeType(): string
    {
        return "text/plain";
    }

    public function getData(): string
    {
        return "Hello, World!";
    }

    public function getSize(): int
    {
        return strlen($this->getData());
    }
}
