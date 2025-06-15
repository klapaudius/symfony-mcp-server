<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Result;

use KLP\KlpMcpServer\Services\ToolService\Result\CollectionToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class CollectionToolResultTest extends TestCase
{
    public function test_constructor_creates_empty_collection(): void
    {
        $collection = new CollectionToolResult;

        $this->assertInstanceOf(CollectionToolResult::class, $collection);
        $this->assertInstanceOf(ToolResultInterface::class, $collection);
    }

    public function test_get_sanitized_result_returns_empty_array_when_no_items(): void
    {
        $collection = new CollectionToolResult;

        $result = $collection->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_add_item_with_single_text_result(): void
    {
        $collection = new CollectionToolResult;
        $textResult = new TextToolResult('Test text');

        $collection->addItem($textResult);

        $result = $collection->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals([
            ['type' => 'text', 'text' => 'Test text'],
        ], $result);
    }

    public function test_add_multiple_items_of_same_type(): void
    {
        $collection = new CollectionToolResult;
        $textResult1 = new TextToolResult('First text');
        $textResult2 = new TextToolResult('Second text');
        $textResult3 = new TextToolResult('Third text');

        $collection->addItem($textResult1);
        $collection->addItem($textResult2);
        $collection->addItem($textResult3);

        $result = $collection->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals([
            ['type' => 'text', 'text' => 'First text'],
            ['type' => 'text', 'text' => 'Second text'],
            ['type' => 'text', 'text' => 'Third text'],
        ], $result);
    }

    public function test_add_multiple_items_of_different_types(): void
    {
        $collection = new CollectionToolResult;

        // Create mock for different result types since we need to test mixed types
        $textResult = new TextToolResult('Test text');

        // Mock image result
        $imageResult = $this->createMock(ToolResultInterface::class);
        $imageResult->method('getSanitizedResult')->willReturn([
            'type' => 'image',
            'data' => 'base64imagedata',
            'mimeType' => 'image/png',
        ]);

        // Mock audio result
        $audioResult = $this->createMock(ToolResultInterface::class);
        $audioResult->method('getSanitizedResult')->willReturn([
            'type' => 'audio',
            'data' => 'base64audiodata',
            'mimeType' => 'audio/mpeg',
        ]);

        $collection->addItem($textResult);
        $collection->addItem($imageResult);
        $collection->addItem($audioResult);

        $result = $collection->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals([
            ['type' => 'text', 'text' => 'Test text'],
            ['type' => 'image', 'data' => 'base64imagedata', 'mimeType' => 'image/png'],
            ['type' => 'audio', 'data' => 'base64audiodata', 'mimeType' => 'audio/mpeg'],
        ], $result);
    }

    public function test_nested_collections_will_throw_exception(): void
    {
        $outerCollection = new CollectionToolResult;
        $innerCollection = new CollectionToolResult;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CollectionToolResult cannot contain other CollectionToolResult');

        $outerCollection->addItem($innerCollection);
    }

    public function test_preserve_item_order(): void
    {
        $collection = new CollectionToolResult;

        // Add items in specific order
        for ($i = 1; $i <= 5; $i++) {
            $collection->addItem(new TextToolResult("Item $i"));
        }

        $result = $collection->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertCount(5, $result);

        // Verify order is preserved
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals('Item '.($i + 1), $result[$i]['text']);
        }
    }

    public function test_handle_custom_tool_result_implementation(): void
    {
        $collection = new CollectionToolResult;

        // Create a custom implementation of ToolResultInterface
        $customResult = new class implements ToolResultInterface
        {
            public function getSanitizedResult(): array
            {
                return [
                    'type' => 'custom',
                    'customField' => 'customValue',
                    'data' => ['nested' => 'structure'],
                ];
            }
        };

        $collection->addItem($customResult);

        $result = $collection->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals([
            [
                'type' => 'custom',
                'customField' => 'customValue',
                'data' => ['nested' => 'structure'],
            ],
        ], $result);
    }

    public function test_large_collection_performance(): void
    {
        $collection = new CollectionToolResult;
        $itemCount = 1000;

        // Add many items
        for ($i = 0; $i < $itemCount; $i++) {
            $collection->addItem(new TextToolResult("Item $i"));
        }

        $startTime = microtime(true);
        $result = $collection->getSanitizedResult();
        $executionTime = microtime(true) - $startTime;

        $this->assertIsArray($result);
        $this->assertCount($itemCount, $result);

        // Assert reasonable performance (should complete in less than 100ms)
        $this->assertLessThan(0.1, $executionTime, 'Collection processing took too long');
    }

    public function test_handle_empty_string_values(): void
    {
        $collection = new CollectionToolResult;

        $collection->addItem(new TextToolResult(''));
        $collection->addItem(new TextToolResult('Non-empty'));
        $collection->addItem(new TextToolResult(''));

        $result = $collection->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals([
            ['type' => 'text', 'text' => ''],
            ['type' => 'text', 'text' => 'Non-empty'],
            ['type' => 'text', 'text' => ''],
        ], $result);
    }
}
