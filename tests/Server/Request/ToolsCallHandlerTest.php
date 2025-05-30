<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Server\Request\ToolsCallHandler;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool;
use KLP\KlpMcpServer\Services\ToolService\ToolParamsValidator;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ToolsCallHandlerTest extends TestCase
{
    private ToolRepository $toolRepository;

    private ToolsCallHandler $toolsCallHandler;

    protected function setUp(): void
    {
        $this->toolRepository = $this->createMock(ToolRepository::class);
        $this->toolsCallHandler = new ToolsCallHandler($this->toolRepository);
    }

    public function test_execute_throws_exception_when_tool_name_is_missing(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Tool name is required');

        $this->toolsCallHandler->execute('tools/call', 1, []);
    }

    public function test_execute_throws_exception_when_tool_not_found(): void
    {
        $this->toolRepository
            ->method('getTool')
            ->with('nonexistent-tool')
            ->willReturn(null);

        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage("Tool 'nonexistent-tool' not found");

        $this->toolsCallHandler->execute('tools/call', 2, ['name' => 'nonexistent-tool']);
    }

    public function test_execute_throws_exception_for_invalid_arguments(): void
    {
        $toolMock = $this->createMock(VersionCheckTool::class);
        $toolMock->method('getInputSchema')->willReturn(['type' => 'object']);

        $this->toolRepository
            ->method('getTool')
            ->with('VersionCheckTool')
            ->willReturn($toolMock);

        $this->expectException(ToolParamsValidatorException::class);

        ToolParamsValidator::validate(['type' => 'object'], ['invalid' => 'data']);

        $this->toolsCallHandler->execute('tools/execute', 3, ['name' => 'VersionCheckTool', 'arguments' => ['invalid' => 'data']]);
    }

    public function test_execute_returns_content_for_tools_call(): void
    {
        $this->toolRepository
            ->method('getTool')
            ->with('HelloWorldTool')
            ->willReturn(new HelloWorldTool);

        $result = $this->toolsCallHandler->execute('tools/call', 4, ['name' => 'HelloWorldTool', 'arguments' => ['name' => 'Success Message']]);

        $this->assertEquals(
            [
                'content' => [
                    ['type' => 'text', 'text' => 'Hello, HelloWorld `Success Message` developer.'],
                ],
            ],
            $result
        );
    }

    public function test_execute_returns_result_for_tools_execute(): void
    {
        $this->toolRepository
            ->method('getTool')
            ->with('HelloWorldTool')
            ->willReturn(new HelloWorldTool);

        $result = $this->toolsCallHandler->execute('tools/execute', 5, ['name' => 'HelloWorldTool', 'arguments' => ['name' => 'Success Message']]);

        $this->assertEquals(
            [
                'result' => 'Hello, HelloWorld `Success Message` developer.',
            ],
            $result
        );
    }

    public function test_is_handle_returns_true_for_tools_call(): void
    {
        $this->assertTrue($this->toolsCallHandler->isHandle('tools/call'));
    }

    public function test_is_handle_returns_true_for_tools_execute(): void
    {
        $this->assertTrue($this->toolsCallHandler->isHandle('tools/execute'));
    }

    public function test_is_handle_returns_false_for_invalid_method(): void
    {
        $this->assertFalse($this->toolsCallHandler->isHandle('invalid/method'));
    }
}
