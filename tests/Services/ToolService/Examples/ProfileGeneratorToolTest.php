<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Examples\ProfileGeneratorTool;
use KLP\KlpMcpServer\Services\ToolService\Result\CollectionToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

#[Small]
class ProfileGeneratorToolTest extends TestCase
{
    private ProfileGeneratorTool $tool;

    private string $mockImagePath;

    protected function setUp(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')
            ->willReturn('/tmp/test-project');

        $this->mockImagePath = '/tmp/test-project/vendor/klapaudius/symfony-mcp-server/docs/assets/avatar_sample.jpg';

        $dir = dirname($this->mockImagePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAAAAAAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAr/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
        file_put_contents($this->mockImagePath, $imageData);

        $this->tool = new ProfileGeneratorTool($kernel);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->mockImagePath)) {
            unlink($this->mockImagePath);
        }

        $dir = dirname($this->mockImagePath);
        while ($dir !== '/tmp' && is_dir($dir)) {
            @rmdir($dir);
            $dir = dirname($dir);
        }
    }

    public function test_get_name(): void
    {
        $this->assertEquals('profile-generator', $this->tool->getName());
    }

    public function test_get_description(): void
    {
        $expectedDescription = 'Generates a user profile with text description and avatar image';
        $this->assertEquals($expectedDescription, $this->tool->getDescription());
    }

    public function test_get_input_schema(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('role', $schema['properties']);
        $this->assertEquals(['name', 'role'], $schema['required']);

        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('The name of the user', $schema['properties']['name']['description']);

        $this->assertEquals('string', $schema['properties']['role']['type']);
        $this->assertEquals('The role or profession of the user', $schema['properties']['role']['description']);
    }

    public function test_get_annotations(): void
    {
        $annotation = $this->tool->getAnnotations();

        $this->assertInstanceOf(ToolAnnotation::class, $annotation);
    }

    public function test_is_streaming(): void
    {
        $this->assertTrue($this->tool->isStreaming());
    }

    public function test_set_progress_notifier(): void
    {
        $progressNotifier = $this->createMock(ProgressNotifierInterface::class);

        $progressNotifier->expects($this->any())
            ->method('sendProgress');

        $this->tool->setProgressNotifier($progressNotifier);

        // Set up sampling client to avoid uninitialized property error
        $samplingClient = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\SamplingClient::class);
        $samplingClient->method('createTextRequest')->willReturn(
            new \KLP\KlpMcpServer\Services\SamplingService\SamplingResponse(
                'assistant',
                new \KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent('text', 'Welcome!')
            )
        );
        $this->tool->setSamplingClient($samplingClient);

        $result = $this->tool->execute(['name' => 'Test', 'role' => 'Test']);
        $this->assertInstanceOf(CollectionToolResult::class, $result);
    }
}
