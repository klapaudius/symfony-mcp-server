<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Server\Request\ToolsCallHandler;
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifier;
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierRepository;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\Examples\StreamingDataTool;
use KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool;
use KLP\KlpMcpServer\Services\ToolService\Result\AudioToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\CollectionToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ImageToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ResourceToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\SamplingAwareToolInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use KLP\KlpMcpServer\Services\ToolService\ToolParamsValidator;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Small]
class ToolsCallHandlerTest extends TestCase
{
    private ToolRepository|MockObject $toolRepository;

    private ProgressNotifierRepository|MockObject $progressNotifierRepository;

    private ToolsCallHandler $toolsCallHandler;

    protected function setUp(): void
    {
        $this->toolRepository = $this->createMock(ToolRepository::class);
        $this->progressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);
        $this->toolsCallHandler = new ToolsCallHandler($this->toolRepository, $this->progressNotifierRepository, null);
    }

    public function test_execute_throws_exception_when_tool_name_is_missing(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Tool name is required');

        $this->toolsCallHandler->execute('tools/call', 'client1', 1, []);
    }

    public function test_execute_throws_exception_when_tool_not_found(): void
    {
        $this->toolRepository
            ->method('getTool')
            ->with('nonexistent-tool')
            ->willReturn(null);

        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage("Tool 'nonexistent-tool' not found");

        $this->toolsCallHandler->execute('tools/call', 'client1', 2, ['name' => 'nonexistent-tool']);
    }

    public function test_execute_throws_exception_for_invalid_arguments(): void
    {
        $toolMock = $this->createMock(VersionCheckTool::class);
        
        // Create a real VersionCheckTool to get its actual schema for the validation test
        $realTool = new VersionCheckTool();
        $toolMock->method('getInputSchema')->willReturn($realTool->getInputSchema());

        $this->toolRepository
            ->method('getTool')
            ->with('VersionCheckTool')
            ->willReturn($toolMock);

        $this->expectException(ToolParamsValidatorException::class);

        $this->toolsCallHandler->execute('tools/call', 'client1', 3, ['name' => 'VersionCheckTool', 'arguments' => ['invalid' => 'data']]);
    }

    public function test_execute_returns_content_for_tools_call(): void
    {
        $this->toolRepository
            ->method('getTool')
            ->with('HelloWorldTool')
            ->willReturn(new HelloWorldTool);

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 4, ['name' => 'HelloWorldTool', 'arguments' => ['name' => 'Success Message']]);

        $this->assertEquals(
            [
                'content' => [
                    ['type' => 'text', 'text' => 'Hello, HelloWorld `Success Message` developer.'],
                ],
            ],
            $result
        );
    }

    public function test_execute_returns_content_for_tools_call_alternative(): void
    {
        $this->toolRepository
            ->method('getTool')
            ->with('HelloWorldTool')
            ->willReturn(new HelloWorldTool);

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 5, ['name' => 'HelloWorldTool', 'arguments' => ['name' => 'Success Message']]);

        $this->assertEquals(
            [
                'content' => [
                    ['type' => 'text', 'text' => 'Hello, HelloWorld `Success Message` developer.'],
                ],
            ],
            $result
        );
    }

    public function test_is_handle_returns_true_for_tools_call(): void
    {
        $this->assertTrue($this->toolsCallHandler->isHandle('tools/call'));
    }

    public function test_is_handle_returns_false_for_tools_execute(): void
    {
        $this->assertFalse($this->toolsCallHandler->isHandle('tools/execute'));
    }

    public function test_is_handle_returns_false_for_invalid_method(): void
    {
        $this->assertFalse($this->toolsCallHandler->isHandle('invalid/method'));
    }

    public function test_execute_with_streaming_tool_and_progress_token(): void
    {
        $streamingTool = $this->createMock(StreamableToolInterface::class);
        $progressNotifier = $this->createMock(ProgressNotifier::class);

        $streamingTool->method('getName')->willReturn('streaming-tool');
        $streamingTool->method('getInputSchema')->willReturn(['type' => 'object']);
        $streamingTool->method('isStreaming')->willReturn(true);
        $streamingTool->method('execute')->willReturn(new TextToolResult('Streaming result'));

        $streamingTool->expects($this->once())
            ->method('setProgressNotifier')
            ->with($progressNotifier);

        $this->toolRepository
            ->method('getTool')
            ->with('streaming-tool')
            ->willReturn($streamingTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('registerToken')
            ->with('progress-123', 'client1')
            ->willReturn($progressNotifier);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with('progress-123');

        $params = [
            'name' => 'streaming-tool',
            'arguments' => [],
            '_meta' => ['progressToken' => 'progress-123'],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Streaming result'],
            ],
        ], $result);
    }

    public function test_execute_with_streaming_tool_without_progress_token(): void
    {
        $streamingTool = $this->createMock(StreamableToolInterface::class);

        $streamingTool->method('getName')->willReturn('streaming-tool');
        $streamingTool->method('getInputSchema')->willReturn(['type' => 'object']);
        $streamingTool->method('isStreaming')->willReturn(true);
        $streamingTool->method('execute')->willReturn(new TextToolResult('Non-streaming result'));

        $streamingTool->expects($this->never())
            ->method('setProgressNotifier');

        $this->toolRepository
            ->method('getTool')
            ->with('streaming-tool')
            ->willReturn($streamingTool);

        $this->progressNotifierRepository
            ->expects($this->never())
            ->method('registerToken');

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'streaming-tool',
            'arguments' => [],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Non-streaming result'],
            ],
        ], $result);
    }

    public function test_execute_with_non_streaming_tool_and_progress_token(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $tool->method('getName')->willReturn('non-streaming-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('isStreaming')->willReturn(false);
        $tool->method('execute')->willReturn(new TextToolResult('Regular result'));

        $tool->expects($this->never())
            ->method('setProgressNotifier');

        $this->toolRepository
            ->method('getTool')
            ->with('non-streaming-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->never())
            ->method('registerToken');

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with('progress-456');

        $params = [
            'name' => 'non-streaming-tool',
            'arguments' => [],
            '_meta' => ['progressToken' => 'progress-456'],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Regular result'],
            ],
        ], $result);
    }

    public function test_execute_with_non_tools_call_method(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $tool->method('getName')->willReturn('any-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('isStreaming')->willReturn(false);
        $tool->method('execute')->willReturn(new TextToolResult('Tool result'));

        $this->toolRepository
            ->method('getTool')
            ->with('any-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'any-tool',
            'arguments' => [],
        ];

        $result = $this->toolsCallHandler->execute('tools/execute', 'client1', 1, $params);

        $this->assertEquals([
            'result' => new TextToolResult('Tool result'),
        ], $result);
    }

    public function test_execute_handles_missing_meta_key(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $tool->method('getName')->willReturn('test-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object', 'properties' => new \stdClass, 'required' => []]);
        $tool->method('isStreaming')->willReturn(true);
        $tool->method('execute')->willReturn(new TextToolResult('Result without meta'));

        $tool->expects($this->never())
            ->method('setProgressNotifier');

        $this->toolRepository
            ->method('getTool')
            ->with('test-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->never())
            ->method('registerToken');

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'test-tool',
            'arguments' => [],
            '_meta' => [], // Empty meta array
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Result without meta'],
            ],
        ], $result);
    }

    public function test_execute_with_null_progress_token(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $tool->method('getName')->willReturn('test-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object', 'properties' => new \stdClass, 'required' => []]);
        $tool->method('isStreaming')->willReturn(true);
        $tool->method('execute')->willReturn(new TextToolResult('Result with null token'));

        $tool->expects($this->never())
            ->method('setProgressNotifier');

        $this->toolRepository
            ->method('getTool')
            ->with('test-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->never())
            ->method('registerToken');

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'test-tool',
            'arguments' => [],
            '_meta' => ['progressToken' => null],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Result with null token'],
            ],
        ], $result);
    }

    public function test_execute_with_image_tool_result(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $imageResult = new ImageToolResult($base64Data, 'image/png');

        $tool->method('getName')->willReturn('image-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('isStreaming')->willReturn(false);
        $tool->method('execute')->willReturn($imageResult);

        $this->toolRepository
            ->method('getTool')
            ->with('image-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'image-tool',
            'arguments' => [],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                [
                    'type' => 'image',
                    'data' => $base64Data,
                    'mimeType' => 'image/png',
                ],
            ],
        ], $result);
    }

    public function test_execute_with_audio_tool_result(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);
        $base64Data = 'UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAC';
        $audioResult = new AudioToolResult($base64Data, 'audio/wav');

        $tool->method('getName')->willReturn('audio-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('isStreaming')->willReturn(false);
        $tool->method('execute')->willReturn($audioResult);

        $this->toolRepository
            ->method('getTool')
            ->with('audio-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'audio-tool',
            'arguments' => [],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                [
                    'type' => 'audio',
                    'data' => $base64Data,
                    'mimeType' => 'audio/wav',
                ],
            ],
        ], $result);
    }

    public function test_execute_with_resource_tool_result(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);
        $resourceResult = new ResourceToolResult(
            'https://example.com/data.json',
            'application/json',
            '{"message": "Resource content"}'
        );

        $tool->method('getName')->willReturn('resource-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('isStreaming')->willReturn(false);
        $tool->method('execute')->willReturn($resourceResult);

        $this->toolRepository
            ->method('getTool')
            ->with('resource-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'resource-tool',
            'arguments' => [],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                [
                    'type' => 'resource',
                    'resource' => [
                        'uri' => 'https://example.com/data.json',
                        'mimeType' => 'application/json',
                        'text' => '{"message": "Resource content"}',
                    ],
                ],
            ],
        ], $result);
    }

    public function test_execute_with_empty_arguments(): void
    {
        $tool = new class implements StreamableToolInterface
        {
            public function getName(): string
            {
                return 'empty-args-tool';
            }

            public function getDescription(): string
            {
                return 'Tool with empty args';
            }

            public function getInputSchema(): StructuredSchema
            {
                return new StructuredSchema();
            }

            public function getAnnotations(): ToolAnnotation
            {
                return new ToolAnnotation;
            }

            public function execute(array $arguments): ToolResultInterface
            {
                return new TextToolResult('Empty args result');
            }

            public function isStreaming(): bool
            {
                return false;
            }

            public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void {}
        };

        $this->toolRepository
            ->method('getTool')
            ->with('empty-args-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, ['name' => 'empty-args-tool']);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Empty args result'],
            ],
        ], $result);
    }

    public function test_execute_with_complex_arguments(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $tool->method('getName')->willReturn('complex-tool');
        $tool->method('getInputSchema')->willReturn([
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                ],
                'count' => [
                    'type' => 'integer',
                ],
            ],
            'required' => [],
        ]);
        $tool->method('isStreaming')->willReturn(false);
        $tool->method('execute')->willReturn(new TextToolResult('Complex result'));

        $this->toolRepository
            ->method('getTool')
            ->with('complex-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'complex-tool',
            'arguments' => [
                'message' => 'test message',
                'count' => 42,
            ],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Complex result'],
            ],
        ], $result);
    }

    public function test_execute_with_integer_progress_token(): void
    {
        $streamingTool = $this->createMock(StreamableToolInterface::class);
        $progressNotifier = $this->createMock(ProgressNotifier::class);

        $streamingTool->method('getName')->willReturn('streaming-tool');
        $streamingTool->method('getInputSchema')->willReturn(['type' => 'object']);
        $streamingTool->method('isStreaming')->willReturn(true);
        $streamingTool->method('execute')->willReturn(new TextToolResult('Integer token result'));

        $streamingTool->expects($this->once())
            ->method('setProgressNotifier')
            ->with($progressNotifier);

        $this->toolRepository
            ->method('getTool')
            ->with('streaming-tool')
            ->willReturn($streamingTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('registerToken')
            ->with(12345, 'client1')
            ->willReturn($progressNotifier);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(12345);

        $params = [
            'name' => 'streaming-tool',
            'arguments' => [],
            '_meta' => ['progressToken' => 12345],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Integer token result'],
            ],
        ], $result);
    }

    public function test_execute_with_real_streaming_data_tool(): void
    {
        $streamingTool = new StreamingDataTool;

        $this->toolRepository
            ->method('getTool')
            ->with('stream-data')
            ->willReturn($streamingTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'stream-data',
            'arguments' => [
                'message' => 'Test streaming',
                'chunks' => 2,
                'delay' => 100,
            ],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertArrayHasKey('content', $result);
        $this->assertCount(1, $result['content']);
        $this->assertEquals('text', $result['content'][0]['type']);
        $this->assertStringContainsString('Test streaming - Chunk 1/2', $result['content'][0]['text']);
        $this->assertStringContainsString('Test streaming - Chunk 2/2', $result['content'][0]['text']);
    }

    public function test_execute_with_sampling_aware_tool_and_sampling_client(): void
    {
        $samplingClient = $this->createMock(SamplingClient::class);
        $samplingAwareTool = $this->createMock(SamplingAwareToolInterface::class);

        $samplingAwareTool->method('getName')->willReturn('sampling-aware-tool');
        $samplingAwareTool->method('getInputSchema')->willReturn(['type' => 'object']);
        $samplingAwareTool->method('execute')->willReturn(new TextToolResult('Sampling aware result'));

        $samplingClient->expects($this->once())
            ->method('setCurrentClientId')
            ->with('client1');

        $samplingAwareTool->expects($this->once())
            ->method('setSamplingClient')
            ->with($samplingClient);

        $this->toolRepository
            ->method('getTool')
            ->with('sampling-aware-tool')
            ->willReturn($samplingAwareTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        // Create handler with sampling client
        $handlerWithSampling = new ToolsCallHandler(
            $this->toolRepository,
            $this->progressNotifierRepository,
            $samplingClient
        );

        $params = [
            'name' => 'sampling-aware-tool',
            'arguments' => [],
        ];

        $result = $handlerWithSampling->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Sampling aware result'],
            ],
        ], $result);
    }

    public function test_execute_with_sampling_aware_tool_without_sampling_client(): void
    {
        $samplingAwareTool = $this->createMock(SamplingAwareToolInterface::class);

        $samplingAwareTool->method('getName')->willReturn('sampling-aware-tool');
        $samplingAwareTool->method('getInputSchema')->willReturn(['type' => 'object']);
        $samplingAwareTool->method('execute')->willReturn(new TextToolResult('Result without sampling'));

        $samplingAwareTool->expects($this->never())
            ->method('setSamplingClient');

        $this->toolRepository
            ->method('getTool')
            ->with('sampling-aware-tool')
            ->willReturn($samplingAwareTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'sampling-aware-tool',
            'arguments' => [],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Result without sampling'],
            ],
        ], $result);
    }

    public function test_execute_with_tool_that_is_both_streamable_and_sampling_aware(): void
    {
        $samplingClient = $this->createMock(SamplingClient::class);
        $progressNotifier = $this->createMock(ProgressNotifier::class);

        // Create a tool that implements both interfaces
        $combinedTool = new class implements SamplingAwareToolInterface, StreamableToolInterface
        {
            private ?ProgressNotifierInterface $progressNotifier = null;

            private ?SamplingClient $samplingClient = null;

            public function getName(): string
            {
                return 'combined-tool';
            }

            public function getDescription(): string
            {
                return 'Tool with both streaming and sampling';
            }

            public function getInputSchema(): StructuredSchema
            {
                return new StructuredSchema();
            }

            public function getAnnotations(): ToolAnnotation
            {
                return new ToolAnnotation;
            }

            public function execute(array $arguments): ToolResultInterface
            {
                $result = 'Combined result';
                if ($this->progressNotifier !== null) {
                    $result .= ' with progress';
                }
                if ($this->samplingClient !== null) {
                    $result .= ' with sampling';
                }

                return new TextToolResult($result);
            }

            public function isStreaming(): bool
            {
                return true;
            }

            public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
            {
                $this->progressNotifier = $progressNotifier;
            }

            public function setSamplingClient(SamplingClient $samplingClient): void
            {
                $this->samplingClient = $samplingClient;
            }
        };

        $samplingClient->expects($this->once())
            ->method('setCurrentClientId')
            ->with('client1');

        $this->toolRepository
            ->method('getTool')
            ->with('combined-tool')
            ->willReturn($combinedTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('registerToken')
            ->with('progress-789', 'client1')
            ->willReturn($progressNotifier);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with('progress-789');

        // Create handler with sampling client
        $handlerWithSampling = new ToolsCallHandler(
            $this->toolRepository,
            $this->progressNotifierRepository,
            $samplingClient
        );

        $params = [
            'name' => 'combined-tool',
            'arguments' => [],
            '_meta' => ['progressToken' => 'progress-789'],
        ];

        $result = $handlerWithSampling->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Combined result with progress with sampling'],
            ],
        ], $result);
    }

    public function test_execute_with_non_sampling_aware_tool_and_sampling_client(): void
    {
        $samplingClient = $this->createMock(SamplingClient::class);
        $regularTool = $this->createMock(StreamableToolInterface::class);

        $regularTool->method('getName')->willReturn('regular-tool');
        $regularTool->method('getInputSchema')->willReturn(['type' => 'object']);
        $regularTool->method('isStreaming')->willReturn(false);
        $regularTool->method('execute')->willReturn(new TextToolResult('Regular tool result'));

        // Sampling client should not be called for non-sampling-aware tools
        $samplingClient->expects($this->never())
            ->method('setCurrentClientId');

        $this->toolRepository
            ->method('getTool')
            ->with('regular-tool')
            ->willReturn($regularTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        // Create handler with sampling client
        $handlerWithSampling = new ToolsCallHandler(
            $this->toolRepository,
            $this->progressNotifierRepository,
            $samplingClient
        );

        $params = [
            'name' => 'regular-tool',
            'arguments' => [],
        ];

        $result = $handlerWithSampling->execute('tools/call', 'client1', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Regular tool result'],
            ],
        ], $result);
    }

    public function test_execute_with_sampling_aware_tool_different_client_ids(): void
    {
        $samplingClient = $this->createMock(SamplingClient::class);
        $samplingAwareTool = $this->createMock(SamplingAwareToolInterface::class);

        $samplingAwareTool->method('getName')->willReturn('sampling-aware-tool');
        $samplingAwareTool->method('getInputSchema')->willReturn(['type' => 'object']);
        $samplingAwareTool->method('execute')->willReturn(new TextToolResult('Result for client2'));

        // Test with different client ID
        $samplingClient->expects($this->once())
            ->method('setCurrentClientId')
            ->with('client2-special-id');

        $samplingAwareTool->expects($this->once())
            ->method('setSamplingClient')
            ->with($samplingClient);

        $this->toolRepository
            ->method('getTool')
            ->with('sampling-aware-tool')
            ->willReturn($samplingAwareTool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        // Create handler with sampling client
        $handlerWithSampling = new ToolsCallHandler(
            $this->toolRepository,
            $this->progressNotifierRepository,
            $samplingClient
        );

        $params = [
            'name' => 'sampling-aware-tool',
            'arguments' => [],
        ];

        $result = $handlerWithSampling->execute('tools/call', 'client2-special-id', 1, $params);

        $this->assertEquals([
            'content' => [
                ['type' => 'text', 'text' => 'Result for client2'],
            ],
        ], $result);
    }

    public function test_execute_with_collection_tool_result(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $textResult1 = new TextToolResult('First result');
        $textResult2 = new TextToolResult('Second result');
        $collectionResult = new CollectionToolResult;
        $collectionResult->addItem($textResult1);
        $collectionResult->addItem($textResult2);

        $tool->method('getName')->willReturn('collection-tool');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('isStreaming')->willReturn(false);
        $tool->method('execute')->willReturn($collectionResult);

        $this->toolRepository
            ->method('getTool')
            ->with('collection-tool')
            ->willReturn($tool);

        $this->progressNotifierRepository
            ->expects($this->once())
            ->method('unregisterToken')
            ->with(null);

        $params = [
            'name' => 'collection-tool',
            'arguments' => [],
        ];

        $result = $this->toolsCallHandler->execute('tools/call', 'client1', 1, $params);

        $this->assertArrayHasKey('content', $result);
        $this->assertCount(2, $result['content']);
        $this->assertEquals(['type' => 'text', 'text' => 'First result'], $result['content'][0]);
        $this->assertEquals(['type' => 'text', 'text' => 'Second result'], $result['content'][1]);
    }

    // Note: Legacy deprecation tests for tools returning string/array instead of ToolResultInterface
    // are not included due to PHP type system constraints in test environment.
    // The deprecation logic is covered by the actual implementation in ToolsCallHandler.
}
