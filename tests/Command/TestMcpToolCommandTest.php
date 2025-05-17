<?php

namespace KLP\KlpMcpServer\Tests\Command;

use KLP\KlpMcpServer\Command\TestMcpToolCommand;
use KLP\KlpMcpServer\Exceptions\TestMcpToolCommandException;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\ToolInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Small]
class TestMcpToolCommandTest extends TestCase
{
    private TestMcpToolCommand $command;
    private ContainerInterface|MockObject $containerMock;
    private InputInterface|MockObject $inputMock;
    private OutputInterface|MockObject $outputMock;
    private SymfonyStyle|MockObject $ioMock;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->inputMock = $this->createMock(InputInterface::class);
        $this->outputMock = $this->createMock(OutputInterface::class);
        $this->ioMock = $this->createMock(SymfonyStyle::class);

        $this->command = new TestMcpToolCommand($this->containerMock);
        $this->command->setApplication($this->createMock(\Symfony\Component\Console\Application::class));
        $this->injectPrivateProperty($this->command, 'input', $this->inputMock);
        $this->injectPrivateProperty($this->command, 'io', $this->ioMock);
    }

    /**
     * Tests that an exception is thrown when no tool is provided
     */
    public function test_get_tool_instance_no_tool_provided_throws_exception(): void
    {
        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn(null);

        $this->expectException(TestMcpToolCommandException::class);
        $this->expectExceptionMessage('No tool specified.');

        $this->invokeGetToolInstanceMethod();
    }

    /**
     * Tests that a tool instance is returned when a valid class name is provided
     */
    public function test_get_tool_instance_valid_class_name_returns_tool_instance(): void
    {
        $toolMock = $this->createMock(ToolInterface::class);
        $this->inputMock
            ->expects($this->once())
            ->method('getArgument')
            ->with('tool')
            ->willReturn(HelloWorldTool::class);

        $this->containerMock
            ->expects($this->once())
            ->method('get')
            ->with(HelloWorldTool::class)
            ->willReturn($toolMock);

        $this->containerMock
            ->expects($this->never())
            ->method('getParameter');

        $this->assertSame($toolMock, $this->invokeGetToolInstanceMethod());
    }

    /**
     * Tests that a tool instance is returned when a matching configured tool is found
     */
    public function test_get_tool_instance_matching_configured_tool_returns_tool_instance(): void
    {
        $identifier = 'custom';
        $configuredTools = ['App\\Tools\\CustomTool'];
        $toolMock = $this->createMock(ToolInterface::class);

        $toolMock->method('getName')->willReturn('custom');

        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn($identifier);

        $this->containerMock
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn($configuredTools);

        $this->containerMock
            ->method('get')
            ->with('App\\Tools\\CustomTool')
            ->willReturn($toolMock);

        $this->assertSame($toolMock, $this->invokeGetToolInstanceMethod());
    }

    /**
     * Tests that an exception is thrown when the tool class does not implement ToolInterface
     */
    public function test_get_tool_instance_invalid_tool_class_throws_exception(): void
    {
        $invalidTool = new \stdClass();

        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn(HelloWorldTool::class);

        $this->containerMock
            ->method('get')
            ->with(HelloWorldTool::class)
            ->willReturn($invalidTool);

        $this->containerMock
            ->expects($this->never())
            ->method('getParameter');

        $this->expectException(TestMcpToolCommandException::class);
        $this->expectExceptionMessage("The class 'stdClass' does not implement ToolInterface.");

        $this->invokeGetToolInstanceMethod();
    }

    /**
     * Tests that an exception is thrown when the tool is not found
     */
    public function test_get_tool_instance_tool_not_found_throws_exception(): void
    {
        $identifier = 'nonexistent_tool';
        $configuredTools = ['App\\Tools\\ValidTool'];

        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn($identifier);

        $this->containerMock
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn($configuredTools);

        $this->containerMock
            ->method('get')
            ->willReturnCallback(function ($class) use ($configuredTools) {
                if (in_array($class, $configuredTools)) {
                    $toolMock = $this->createMock(ToolInterface::class);
                    $toolMock->method('getName')->willReturn('Valid Tool');
                    return $toolMock;
                }
                return null;
            });

        $this->expectException(TestMcpToolCommandException::class);
        $this->expectExceptionMessage("Tool 'nonexistent_tool' not found.");

        $this->invokeGetToolInstanceMethod();
    }

    private function invokeGetToolInstanceMethod(): ToolInterface
    {
        $reflection = new \ReflectionMethod(TestMcpToolCommand::class, 'getToolInstance');

        return $reflection->invoke($this->command);
    }

    private function injectPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $propertyName);
        $reflection->setValue($object, $value);
    }

    /**
     * Tests that the displaySchema method correctly displays the schema
     * for a tool with a simple input schema.
     */
    public function test_display_schema_for_tool_with_simple_schema_displays_correct_output(): void
    {
        $toolMock = $this->createMock(ToolInterface::class);
        $toolMock->method('getName')->willReturn('SimpleTool');
        $toolMock->method('getDescription')->willReturn('A simple tool.');
        $toolMock->method('getInputSchema')->willReturn([
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'A simple string parameter.',
                ],
            ],
            'required' => ['param1'],
        ]);

        $invocations = [
            [
                'Testing tool: SimpleTool (' . get_class($toolMock) . ')',
                'Description: A simple tool.',
            ],
            [
                'Input schema:',
                '- param1: string (required)',
                '  Description: A simple string parameter.',
            ]
        ];
        $this->ioMock
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('text')
            ->with(
                $this->callback(function ($text) use ($invocations, $matcher) {
                    $this->assertEquals($text, $invocations[$matcher->numberOfInvocations() - 1]);
                    return true;
                })
            );

        $this->ioMock->expects($this->once())->method('newLine');

        $this->command->displaySchema($toolMock);
    }

    /**
     * Tests that the displaySchema method correctly displays
     * the schema for a tool with a nested object input schema.
     */
    public function test_display_schema_for_tool_with_nested_object_schema_displays_correct_output(): void
    {
        $toolMock = $this->createMock(ToolInterface::class);
        $toolMock->method('getName')->willReturn('NestedTool');
        $toolMock->method('getDescription')->willReturn('A tool with nested schema.');
        $toolMock->method('getInputSchema')->willReturn([
            'properties' => [
                'objectParam' => [
                    'type' => 'object',
                    'description' => 'A nested object parameter.',
                    'properties' => [
                        'subParam' => [
                            'type' => 'integer',
                            'description' => 'An integer inside the object.',
                        ],
                    ],
                    'required' => ['subParam'],
                ],
            ],
            'required' => ['objectParam'],
        ]);

        $invocations = [
            [
                'Testing tool: NestedTool (' . get_class($toolMock) . ')',
                'Description: A tool with nested schema.',
            ],
            [
                'Input schema:',
                '- objectParam: object (required)',
                '  Description: A nested object parameter.',
                '  Properties:',
                '    - subParam: integer (required)',
                '      Description: An integer inside the object.',
            ]
        ];
        $this->ioMock
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('text')
            ->with(
                $this->callback(function ($text) use ($invocations, $matcher) {
                    $this->assertEquals($text, $invocations[$matcher->numberOfInvocations() - 1]);
                    return true;
                })
            );

        $this->ioMock->expects($this->once())->method('newLine');

        $this->command->displaySchema($toolMock);
    }

    /**
     * Tests that the displaySchema method correctly displays
     * the schema for a tool with array-based input schema.
     */
    public function test_display_schema_for_tool_with_array_schema_displays_correct_output(): void
    {
        $toolMock = $this->createMock(ToolInterface::class);
        $toolMock->method('getName')->willReturn('ArrayTool');
        $toolMock->method('getDescription')->willReturn('A tool with array schema.');
        $toolMock->method('getInputSchema')->willReturn([
            'properties' => [
                'arrayParam' => [
                    'type' => 'array',
                    'description' => 'An array of strings.',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ]);

        $invocations = [
            [
                'Testing tool: ArrayTool (' . get_class($toolMock) . ')',
                'Description: A tool with array schema.',
            ],
            [
                'Input schema:',
                '- arrayParam: array (optional)',
                '  Description: An array of strings.',
                '  Items: string',
            ]
        ];
        $this->ioMock
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('text')
            ->with($this->callback(function ($text) use ($invocations, $matcher) {
                $this->assertEquals($text, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }));

        $this->ioMock->expects($this->once())->method('newLine');

        $this->command->displaySchema($toolMock);
    }
}
