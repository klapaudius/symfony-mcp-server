<?php

namespace KLP\KlpMcpServer\Tests\Services\ResourceService;

use KLP\KlpMcpServer\Services\ResourceService\Resource;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ResourceTest extends TestCase
{
    public function test_resource_implements_resource_interface(): void
    {
        $resource = new Resource(
            'resource://test',
            'Test Resource',
            'A test resource',
            'text/plain',
            'Test data'
        );

        $this->assertSame('resource://test', $resource->getUri());
        $this->assertSame('Test Resource', $resource->getName());
        $this->assertSame('A test resource', $resource->getDescription());
        $this->assertSame('text/plain', $resource->getMimeType());
        $this->assertSame('Test data', $resource->getData());
        $this->assertSame(9, $resource->getSize());
    }

    public function test_resource_setters(): void
    {
        $resource = new Resource(
            'resource://test',
            'Test Resource',
            'A test resource',
            'text/plain',
            'Test data'
        );

        $resource->setName('Updated Resource');
        $this->assertSame('Updated Resource', $resource->getName());

        $resource->setDescription('An updated test resource');
        $this->assertSame('An updated test resource', $resource->getDescription());

        $resource->setMimeType('application/json');
        $this->assertSame('application/json', $resource->getMimeType());

        $resource->setData('{"test": "data"}');
        $this->assertSame('{"test": "data"}', $resource->getData());
        $this->assertSame(16, $resource->getSize());
    }
}
