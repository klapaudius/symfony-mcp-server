<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\SamplingResponse;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\ResourceService\Examples\DynamicAnalysisResource;
use PHPUnit\Framework\TestCase;

class DynamicAnalysisResourceTest extends TestCase
{
    private DynamicAnalysisResource $resource;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4); // Points to the project root
        $this->resource = new DynamicAnalysisResource($this->projectRoot);
    }

    public function testGetName(): void
    {
        $this->assertEquals('Symfony Component Analysis', $this->resource->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertEquals(
            'AI-powered analysis of Symfony project components (controllers, services, entities, bundles)',
            $this->resource->getDescription()
        );
    }

    public function testGetMimeType(): void
    {
        $this->assertEquals('text/markdown', $this->resource->getMimeType());
    }

    public function testGetUriTemplate(): void
    {
        $this->assertEquals('analysis://{type}/{name}', $this->resource->getUriTemplate());
    }

    public function testGetUri(): void
    {
        $this->assertEquals('analysis://', $this->resource->getUri());
    }

    public function testGetData(): void
    {
        $data = $this->resource->getData();

        $this->assertStringContainsString('# Symfony Component Analysis', $data);
        $this->assertStringContainsString('## Available Analysis Types', $data);
        $this->assertStringContainsString('controller', $data);
        $this->assertStringContainsString('service', $data);
        $this->assertStringContainsString('entity', $data);
        $this->assertStringContainsString('bundle', $data);
    }

    public function testResourceExistsWithValidControllerUri(): void
    {
        // We know MCPServer exists in the src directory
        $exists = $this->resource->resourceExists('analysis://service/MCPServer');
        $this->assertTrue($exists);
    }

    public function testResourceExistsWithInvalidUri(): void
    {
        $this->assertFalse($this->resource->resourceExists('analysis://invalid/NonExistent'));
        $this->assertFalse($this->resource->resourceExists('invalid://format'));
        $this->assertFalse($this->resource->resourceExists('analysis://controller/NonExistentController'));
    }

    public function testGetResourceWithValidUri(): void
    {
        $resource = $this->resource->getResource('analysis://service/MCPServer');

        $this->assertNotNull($resource);
        $this->assertEquals('analysis://service/MCPServer', $resource->getUri());
        $this->assertEquals('Service Analysis: MCPServer', $resource->getName());
        $this->assertEquals('AI-powered analysis of service "MCPServer"', $resource->getDescription());
        $this->assertEquals('text/markdown', $resource->getMimeType());
    }

    public function testGetResourceWithInvalidUri(): void
    {
        $resource = $this->resource->getResource('analysis://controller/NonExistentController');
        $this->assertNull($resource);

        $resource = $this->resource->getResource('invalid://uri');
        $this->assertNull($resource);
    }

    public function testGetResourceDataWithoutSamplingClient(): void
    {
        $resource = $this->resource->getResource('analysis://service/MCPServer');
        $this->assertNotNull($resource);

        $data = $resource->getData();

        $this->assertStringContainsString('# Service Analysis: MCPServer', $data);
        $this->assertStringContainsString('**Note:** AI-powered analysis is currently unavailable.', $data);
        $this->assertStringContainsString('## Component Type', $data);
        $this->assertStringContainsString('## General Recommendations', $data);
    }

    public function testGetResourceDataWithSamplingClient(): void
    {
        $mockSamplingContent = $this->createMock(SamplingContent::class);
        $mockSamplingContent->expects($this->once())
            ->method('getText')
            ->willReturn("# AI Analysis of MCPServer\n\nThis service implements the MCP protocol...");

        $mockSamplingResponse = $this->createMock(SamplingResponse::class);
        $mockSamplingResponse->expects($this->once())
            ->method('getContent')
            ->willReturn($mockSamplingContent);

        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->once())
            ->method('createTextRequest')
            ->willReturn($mockSamplingResponse);

        $this->resource->setSamplingClient($mockSamplingClient);

        $resource = $this->resource->getResource('analysis://service/MCPServer');
        $this->assertNotNull($resource);

        // Pass the sampling client to the resource
        if (method_exists($resource, 'setSamplingClient')) {
            $resource->setSamplingClient($mockSamplingClient);
        }

        $data = $resource->getData();

        $this->assertStringContainsString('# AI Analysis of MCPServer', $data);
        $this->assertStringContainsString('This service implements the MCP protocol', $data);
    }

    public function testGetResourceDataWithSamplingClientError(): void
    {
        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->once())
            ->method('createTextRequest')
            ->willThrowException(new \Exception('API Error'));

        $this->resource->setSamplingClient($mockSamplingClient);

        $resource = $this->resource->getResource('analysis://service/MCPServer');
        $this->assertNotNull($resource);

        // Pass the sampling client to the resource
        if (method_exists($resource, 'setSamplingClient')) {
            $resource->setSamplingClient($mockSamplingClient);
        }

        $data = $resource->getData();

        $this->assertStringContainsString('**Note:** AI-powered analysis is currently unavailable.', $data);
        $this->assertStringContainsString('**Error during analysis:** API Error', $data);
    }

    public function testCachedData(): void
    {
        $mockSamplingContent = $this->createMock(SamplingContent::class);
        $mockSamplingContent->expects($this->once())
            ->method('getText')
            ->willReturn("# Cached Analysis");

        $mockSamplingResponse = $this->createMock(SamplingResponse::class);
        $mockSamplingResponse->expects($this->once())
            ->method('getContent')
            ->willReturn($mockSamplingContent);

        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->once()) // Should only be called once due to caching
            ->method('createTextRequest')
            ->willReturn($mockSamplingResponse);

        $this->resource->setSamplingClient($mockSamplingClient);

        $resource = $this->resource->getResource('analysis://service/MCPServer');
        $this->assertNotNull($resource);

        if (method_exists($resource, 'setSamplingClient')) {
            $resource->setSamplingClient($mockSamplingClient);
        }

        // First call
        $data1 = $resource->getData();
        $this->assertStringContainsString('# Cached Analysis', $data1);

        // Second call should use cache
        $data2 = $resource->getData();
        $this->assertEquals($data1, $data2);
    }

    public function testDifferentAnalysisTypes(): void
    {
        // Test service type (we know MCPServer exists)
        $resource = $this->resource->getResource('analysis://service/MCPServer');
        $this->assertNotNull($resource);
        $this->assertEquals('analysis://service/MCPServer', $resource->getUri());
        $this->assertStringContainsString('Service', $resource->getName());
        $this->assertStringContainsString('service', $resource->getDescription());

        // Test non-existent resources return null
        $types = [
            'controller' => 'analysis://controller/NonExistentController',
            'entity' => 'analysis://entity/NonExistentEntity',
            'bundle' => 'analysis://bundle/NonExistentBundle'
        ];

        foreach ($types as $uri) {
            $resource = $this->resource->getResource($uri);
            $this->assertNull($resource, "Resource for $uri should be null");
        }
    }
}
