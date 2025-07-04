<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\ResourceService\Examples\ProjectSummaryResource;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\SamplingResponse;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ProjectSummaryResourceTest extends TestCase
{
    private ProjectSummaryResource $resource;

    protected function setUp(): void
    {
        $this->resource = new ProjectSummaryResource(__DIR__ . '/../../../../');
    }

    public function test_get_uri(): void
    {
        $this->assertEquals('project://summary.md', $this->resource->getUri());
    }

    public function test_get_name(): void
    {
        $this->assertEquals('Project Summary', $this->resource->getName());
    }

    public function test_get_description(): void
    {
        $this->assertEquals(
            'AI-generated summary of the current project structure and key files',
            $this->resource->getDescription()
        );
    }

    public function test_get_mime_type(): void
    {
        $this->assertEquals('text/plain', $this->resource->getMimeType());
    }

    public function test_get_data_without_sampling(): void
    {
        $data = $this->resource->getData();

        $this->assertStringContainsString('# Project Summary', $data);
        $this->assertStringContainsString('This is a Symfony project', $data);
        $this->assertStringContainsString('## Project Structure', $data);
        $this->assertStringContainsString('Model Context Protocol (MCP) server', $data);
    }

    public function test_get_data_with_sampling(): void
    {
        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->once())
            ->method('canSample')
            ->willReturn(true);

        $mockResponse = $this->createMock(SamplingResponse::class);
        $mockContent = $this->createMock(SamplingContent::class);
        $mockContent->expects($this->once())
            ->method('getText')
            ->willReturn("# AI-Generated Project Summary\n\nThis is a comprehensive analysis of the project...");

        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn($mockContent);

        $mockSamplingClient->expects($this->once())
            ->method('createTextRequest')
            ->with(
                $this->stringContains('Generate a comprehensive markdown summary'),
                $this->isInstanceOf(ModelPreferences::class),
                null,
                3000
            )
            ->willReturn($mockResponse);

        $this->resource->setSamplingClient($mockSamplingClient);

        $data = $this->resource->getData();

        $this->assertStringContainsString('# AI-Generated Project Summary', $data);
        $this->assertStringContainsString('This is a comprehensive analysis', $data);
        $this->assertStringContainsString('*This summary was generated using AI analysis', $data);
    }

    public function test_get_data_with_sampling_failure(): void
    {
        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->once())
            ->method('canSample')
            ->willReturn(true);

        $mockSamplingClient->expects($this->once())
            ->method('createTextRequest')
            ->willThrowException(new \Exception('Sampling service unavailable'));

        $this->resource->setSamplingClient($mockSamplingClient);

        $data = $this->resource->getData();

        // Should fall back to static summary
        $this->assertStringContainsString('# Project Summary', $data);
        $this->assertStringContainsString('*Note: Dynamic summary generation failed: Sampling service unavailable*', $data);
    }

    public function test_get_data_caches_result(): void
    {
        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->any())
            ->method('canSample')
            ->willReturn(true);

        $mockResponse = $this->createMock(SamplingResponse::class);
        $mockContent = $this->createMock(SamplingContent::class);
        $mockContent->expects($this->once())
            ->method('getText')
            ->willReturn("# Cached Summary");

        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn($mockContent);

        // Should only be called once due to caching
        $mockSamplingClient->expects($this->once())
            ->method('createTextRequest')
            ->willReturn($mockResponse);

        $this->resource->setSamplingClient($mockSamplingClient);

        // First call
        $data1 = $this->resource->getData();
        $this->assertStringContainsString('# Cached Summary', $data1);

        // Second call should use cache
        $data2 = $this->resource->getData();
        $this->assertEquals($data1, $data2);
    }

    public function test_set_sampling_client_clears_cache(): void
    {
        $mockSamplingClient1 = $this->createMock(SamplingClient::class);
        $mockSamplingClient1->expects($this->once())
            ->method('canSample')
            ->willReturn(true);

        $mockResponse1 = $this->createMock(SamplingResponse::class);
        $mockContent1 = $this->createMock(SamplingContent::class);
        $mockContent1->expects($this->once())
            ->method('getText')
            ->willReturn("# First Summary");

        $mockResponse1->expects($this->once())
            ->method('getContent')
            ->willReturn($mockContent1);

        $mockSamplingClient1->expects($this->once())
            ->method('createTextRequest')
            ->willReturn($mockResponse1);

        $this->resource->setSamplingClient($mockSamplingClient1);
        $data1 = $this->resource->getData();

        // Set a new sampling client
        $mockSamplingClient2 = $this->createMock(SamplingClient::class);
        $mockSamplingClient2->expects($this->once())
            ->method('canSample')
            ->willReturn(true);

        $mockResponse2 = $this->createMock(SamplingResponse::class);
        $mockContent2 = $this->createMock(SamplingContent::class);
        $mockContent2->expects($this->once())
            ->method('getText')
            ->willReturn("# Second Summary");

        $mockResponse2->expects($this->once())
            ->method('getContent')
            ->willReturn($mockContent2);

        $mockSamplingClient2->expects($this->once())
            ->method('createTextRequest')
            ->willReturn($mockResponse2);

        $this->resource->setSamplingClient($mockSamplingClient2);
        $data2 = $this->resource->getData();

        $this->assertStringContainsString('# First Summary', $data1);
        $this->assertStringContainsString('# Second Summary', $data2);
        $this->assertNotEquals($data1, $data2);
    }

    public function test_get_size(): void
    {
        $data = $this->resource->getData();
        $this->assertEquals(strlen($data), $this->resource->getSize());
    }
}
