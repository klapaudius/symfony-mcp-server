<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Server\Request\ResourcesReadHandler;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Class ResourcesReadHandlerTest
 *
 * Tests for the ResourcesReadHandler class, specifically the `execute` method.
 */
#[Small]
class ResourcesReadHandlerTest extends TestCase
{
    private ResourceRepository $resourceRepository;

    private ResourcesReadHandler $handler;

    protected function setUp(): void
    {
        $this->resourceRepository = $this->createMock(ResourceRepository::class);
        $this->handler = new ResourcesReadHandler($this->resourceRepository);
    }

    /**
     * Tests that execute method correctly processes a text resource.
     * Verifies the response structure when a resource with text MIME type is found.
     */
    public function test_execute_resource_found_with_text_mime_type(): void
    {
        $resourceMock = $this->createMock(ResourceInterface::class);
        $resourceMock->method('getUri')->willReturn('test-uri');
        $resourceMock->method('getMimeType')->willReturn('text/plain');
        $resourceMock->method('getData')->willReturn('Sample text data');

        $this->resourceRepository
            ->method('getResource')
            ->with('test-uri')
            ->willReturn($resourceMock);

        $result = $this->handler->execute('resources/read', 'test-client', 123, ['uri' => 'test-uri']);

        $this->assertEquals([
            'contents' => [
                [
                    'uri' => 'test-uri',
                    'mimeType' => 'text/plain',
                    'text' => 'Sample text data',
                ],
            ],
        ], $result);
    }

    /**
     * Tests that execute method correctly processes a binary resource.
     * Verifies the response structure when a resource with non-text MIME type is found.
     */
    public function test_execute_resource_found_with_non_text_mime_type(): void
    {
        $resourceMock = $this->createMock(ResourceInterface::class);
        $resourceMock->method('getUri')->willReturn('image-uri');
        $resourceMock->method('getMimeType')->willReturn('image/png');
        $resourceMock->method('getData')->willReturn('binarydata123');

        $this->resourceRepository
            ->method('getResource')
            ->with('image-uri')
            ->willReturn($resourceMock);

        $result = $this->handler->execute('resources/read', 'test-client', 456, ['uri' => 'image-uri']);

        $this->assertEquals([
            'contents' => [
                [
                    'uri' => 'image-uri',
                    'mimeType' => 'image/png',
                    'blob' => 'binarydata123',
                ],
            ],
        ], $result);
    }

    /**
     * Tests that execute method returns an empty result when a resource is not found.
     */
    public function test_execute_resource_not_found(): void
    {
        $this->resourceRepository
            ->method('getResource')
            ->with('unknown-uri')
            ->willReturn(null);

        $result = $this->handler->execute('resources/read', 'test-client', 789, ['uri' => 'unknown-uri']);

        $this->assertEquals([], $result);
    }

    /**
     * Tests that isHandle method returns true for the valid method "resources/read".
     */
    public function test_is_handle_with_valid_method(): void
    {
        $this->assertTrue($this->handler->isHandle('resources/read'));
    }

    /**
     * Tests that isHandle method returns false for invalid methods.
     */
    public function test_is_handle_with_invalid_method(): void
    {
        $this->assertFalse($this->handler->isHandle('invalid-method'));
        $this->assertFalse($this->handler->isHandle('another/invalid'));
        $this->assertFalse($this->handler->isHandle(''));
    }
}
