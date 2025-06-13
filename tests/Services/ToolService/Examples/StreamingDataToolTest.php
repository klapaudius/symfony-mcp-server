<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifier;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Examples\StreamingDataTool;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class StreamingDataToolTest extends TestCase
{
    private StreamingDataTool $tool;

    protected function setUp(): void
    {
        $this->tool = new StreamingDataTool();
    }

    public function test_get_name(): void
    {
        $this->assertEquals('stream-data', $this->tool->getName());
    }

    public function test_get_description(): void
    {
        $expectedDescription = 'Demonstrates streaming data processing with progress notifications. Simulates processing a dataset with real-time progress updates.';
        $this->assertEquals($expectedDescription, $this->tool->getDescription());
    }

    public function test_get_input_schema(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('message', $schema['properties']);
        $this->assertArrayHasKey('chunks', $schema['properties']);
        $this->assertArrayHasKey('delay', $schema['properties']);
        $this->assertEquals(['message'], $schema['required']);

        $this->assertEquals('string', $schema['properties']['message']['type']);
        $this->assertEquals('integer', $schema['properties']['chunks']['type']);
        $this->assertEquals(1, $schema['properties']['chunks']['minimum']);
        $this->assertEquals(10, $schema['properties']['chunks']['maximum']);
        $this->assertEquals(5, $schema['properties']['chunks']['default']);

        $this->assertEquals('integer', $schema['properties']['delay']['type']);
        $this->assertEquals(100, $schema['properties']['delay']['minimum']);
        $this->assertEquals(2000, $schema['properties']['delay']['maximum']);
        $this->assertEquals(500, $schema['properties']['delay']['default']);
    }

    public function test_get_annotations(): void
    {
        $annotation = $this->tool->getAnnotations();

        $this->assertInstanceOf(ToolAnnotation::class, $annotation);
        $this->assertTrue($annotation->isReadOnlyHint());
        $this->assertFalse($annotation->isDestructiveHint());
        $this->assertTrue($annotation->isIdempotentHint());
        $this->assertFalse($annotation->isOpenWorldHint());
    }

    public function test_is_streaming(): void
    {
        $this->assertTrue($this->tool->isStreaming());
    }

    public function test_execute_with_default_chunks_arguments(): void
    {
        $result = $this->tool->execute(['message' => 'Test message', 'delay' => 100]);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $content = $result->getSanitizedResult()['text'];

        $this->assertStringContainsString('Test message - Chunk 1/5', $content);
        $this->assertStringContainsString('Test message - Chunk 5/5', $content);
    }

    public function test_execute_with_custom_chunks(): void
    {
        $result = $this->tool->execute([
            'message' => 'Custom test',
            'chunks' => 3,
            'delay' => 100
        ]);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $content = $result->getSanitizedResult()['text'];

        $this->assertStringContainsString('Custom test - Chunk 1/3', $content);
        $this->assertStringContainsString('Custom test - Chunk 3/3', $content);
        $this->assertStringNotContainsString('Custom test - Chunk 4/3', $content);
    }

    public function test_execute_with_minimal_delay(): void
    {
        $startTime = microtime(true);

        $result = $this->tool->execute([
            'message' => 'Fast test',
            'chunks' => 2,
            'delay' => 100,
        ]);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to ms

        $this->assertInstanceOf(TextToolResult::class, $result);
        $this->assertLessThan(1000, $executionTime); // Should complete in less than 1 second
    }

    public function test_set_progress_notifier(): void
    {
        $progressNotifier = $this->createMock(ProgressNotifier::class);

        $this->tool->setProgressNotifier($progressNotifier);

        // Verify that the progress notifier is called during execution
        $progressNotifier->expects($this->atLeastOnce())
            ->method('sendProgress');

        $this->tool->execute(['message' => 'Progress test', 'chunks' => 2]);
    }

    public function test_set_progress_token(): void
    {
        $this->tool->setProgressToken('test-token');

        // The token is set but not directly testable since it's private
        // This test ensures the method exists and doesn't throw an exception
        $this->assertTrue(true);
    }

    public function test_execute_handles_progress_notification_failure(): void
    {
        $progressNotifier = $this->createMock(ProgressNotifier::class);
        $progressNotifier->method('sendProgress')
            ->willThrowException(new \Exception('Progress notification failed'));

        $this->tool->setProgressNotifier($progressNotifier);

        // Should not throw an exception even if progress notification fails
        $result = $this->tool->execute(['message' => 'Error test', 'chunks' => 1, 'delay' => 100]);

        $this->assertInstanceOf(TextToolResult::class, $result);
    }

    public function test_execute_with_empty_arguments(): void
    {
        $result = $this->tool->execute([]);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $content = $result->getSanitizedResult()['text'];

        $this->assertStringContainsString('Hello, streaming world! - Chunk 1/5', $content);
    }
}
