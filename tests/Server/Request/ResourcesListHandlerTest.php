<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Server\Request\ResourcesListHandler;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ResourcesListHandlerTest
 *
 * Tests for the ResourcesListHandler class, specifically the `execute` method.
 */
#[Small]
class ResourcesListHandlerTest extends TestCase
{
    private ResourceRepository|MockObject $resourceRepositoryMock;

    private ResourcesListHandler $resourcesListHandler;

    protected function setUp(): void
    {
        $this->resourceRepositoryMock = $this->createMock(ResourceRepository::class);
        $this->resourcesListHandler = new ResourcesListHandler($this->resourceRepositoryMock);
    }

    /**
     * Tests that the execute method returns the correct resource schemas.
     */
    public function test_execute_returns_resource_schemas(): void
    {
        $expectedSchemas = [
            ['id' => 1, 'name' => 'Resource A'],
            ['id' => 2, 'name' => 'Resource B'],
        ];

        $this->resourceRepositoryMock
            ->expects($this->once())
            ->method('getResourceSchemas')
            ->willReturn($expectedSchemas);

        $result = $this->resourcesListHandler->execute('resources/list', 123);

        $this->assertArrayHasKey('resources', $result);
        $this->assertEquals($expectedSchemas, $result['resources']);
    }

    /**
     * Tests that isHandle returns true for 'resources/list'.
     */
    public function test_is_handle_returns_true_for_resources_list(): void
    {
        $result = $this->resourcesListHandler->isHandle('resources/list');
        $this->assertTrue($result);
    }

    /**
     * Tests that isHandle returns false for a non-matching method.
     */
    public function test_is_handle_returns_false_for_non_matching_method(): void
    {
        $result = $this->resourcesListHandler->isHandle('non-matching/method');
        $this->assertFalse($result);
    }
}
