<?php

namespace KLP\KlpMcpServer\Tests\Command;

use KLP\KlpMcpServer\Command\TestMcpToolCommand;
use KLP\KlpMcpServer\Exceptions\TestMcpToolCommandException;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Small]
class TestMcpToolCommandTest extends TestCase
{
    private TestMcpToolCommand $command;

    private ContainerInterface|MockObject $containerMock;

    private ToolRepository|MockObject $toolRepositoryMock;

    private InputInterface|MockObject $inputMock;

    private OutputInterface|MockObject $outputMock;

    private SymfonyStyle|MockObject $ioMock;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->toolRepositoryMock = $this->createMock(ToolRepository::class);
        $this->inputMock = $this->createMock(InputInterface::class);
        $this->outputMock = $this->createMock(OutputInterface::class);
        $this->ioMock = $this->createMock(SymfonyStyle::class);

        $this->command = new TestMcpToolCommand($this->toolRepositoryMock, $this->containerMock);
        $this->command->setApplication($this->createMock(Application::class));
        $this->injectPrivateProperty($this->command, 'input', $this->inputMock);
        $this->injectPrivateProperty($this->command, 'io', $this->ioMock);
    }

    /**
     * Tests that an exception is thrown when no tool is provided
     */
    public function test_get_tool_instance_no_tool_provided_and_no_tool_configured_throws_exception(): void
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
     * Tests that an exception is thrown when no tool is provided
     */
    public function test_get_tool_instance_no_tool_provided_ask_choice_from_configured_tools(): void
    {
        $helloWorldTool = new HelloWorldTool;
        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([
                'hello-world' => $helloWorldTool
            ]);
        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn(null);

        $this->ioMock
            ->expects($this->once())
            ->method('choice')
            ->with('Select a tool to test', ['hello-world ('.HelloWorldTool::class.')']);

        $this->invokeGetToolInstanceMethod();
    }

    /**
     * Tests that a tool instance is returned when a valid class name is provided
     */
    public function test_get_tool_instance_valid_class_name_returns_tool_instance(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
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
        $toolMock = $this->createMock(StreamableToolInterface::class);

        $toolMock->method('getName')->willReturn('custom');

        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn($identifier);

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([
                'custom' => $toolMock
            ]);

        $this->assertSame($toolMock, $this->invokeGetToolInstanceMethod());
    }

    /**
     * Tests that an exception is thrown when the tool class does not implement StreamableToolInterface
     */
    public function test_get_tool_instance_invalid_tool_class_throws_exception(): void
    {
        $invalidTool = new \stdClass;

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
        $this->expectExceptionMessage("The class 'stdClass' does not implement StreamableToolInterface.");

        $this->invokeGetToolInstanceMethod();
    }

    /**
     * Tests that an exception is thrown when the tool is not found
     */
    public function test_get_tool_instance_tool_not_found_throws_exception(): void
    {
        $identifier = 'nonexistent_tool';
        $validToolMock = $this->createMock(StreamableToolInterface::class);
        $validToolMock->method('getName')->willReturn('Valid Tool');

        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn($identifier);

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([
                'Valid Tool' => $validToolMock
            ]);

        $this->expectException(TestMcpToolCommandException::class);
        $this->expectExceptionMessage("Tool 'nonexistent_tool' not found.");

        $this->invokeGetToolInstanceMethod();
    }

    private function invokeGetToolInstanceMethod(): StreamableToolInterface
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
        $toolMock = $this->createMock(StreamableToolInterface::class);
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
                'Testing tool: SimpleTool ('.get_class($toolMock).')',
                'Description: A simple tool.',
            ],
            [
                'Input schema:',
                '- param1: string (required)',
                '  Description: A simple string parameter.',
            ],
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
        $toolMock = $this->createMock(StreamableToolInterface::class);
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
                'Testing tool: NestedTool ('.get_class($toolMock).')',
                'Description: A tool with nested schema.',
            ],
            [
                'Input schema:',
                '- objectParam: object (required)',
                '  Description: A nested object parameter.',
                '  Properties:',
                '    - subParam: integer (required)',
                '      Description: An integer inside the object.',
            ],
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
        $toolMock = $this->createMock(StreamableToolInterface::class);
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
                'Testing tool: ArrayTool ('.get_class($toolMock).')',
                'Description: A tool with array schema.',
            ],
            [
                'Input schema:',
                '- arrayParam: array (optional)',
                '  Description: An array of strings.',
                '  Items: string',
            ],
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

    /**
     * Tests that valid JSON input from the --input option is correctly parsed and returned as an array.
     */
    public function test_get_input_data_from_option_valid_json_returns_array(): void
    {
        $validJson = '{"key": "value", "number": 42}';

        $this->inputMock
            ->method('getOption')
            ->with('input')
            ->willReturn($validJson);

        $result = $this->invokePrivateMethod('getInputDataFromOption');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('key', $result);
        $this->assertEquals('value', $result['key']);
        $this->assertArrayHasKey('number', $result);
        $this->assertEquals(42, $result['number']);
    }

    /**
     * Tests that invalid JSON input from the --input option displays an error message.
     */
    public function test_get_input_data_from_option_invalid_json_throws_error(): void
    {
        $invalidJson = '{"key": "value"';

        $this->inputMock
            ->method('getOption')
            ->with('input')
            ->willReturn($invalidJson);

        $this->ioMock
            ->expects($this->once())
            ->method('error')
            ->with('Invalid JSON input: Syntax error');

        $result = $this->invokePrivateMethod('getInputDataFromOption');

        $this->assertNull($result);
    }

    /**
     * Tests that when the --input option is not provided, the method returns null.
     */
    public function test_get_input_data_from_option_empty_input_option_returns_null(): void
    {
        $this->inputMock
            ->method('getOption')
            ->with('input')
            ->willReturn(null);

        $result = $this->invokePrivateMethod('getInputDataFromOption');

        $this->assertNull($result);
    }

    /**
     * Helper method to invoke private methods on the command class.
     */
    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod(TestMcpToolCommand::class, $methodName);

        return $reflection->invokeArgs($this->command, $args);
    }

    /**
     * Tests that when no tools are configured, a warning is displayed.
     */
    public function test_list_all_tools_when_no_tools_are_configured_displays_warning(): void
    {
        $this->inputMock
            ->expects($this->once())
            ->method('getOption')
            ->with('list')
            ->willReturn('list');

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No MCP tools are configured. Add tools in config/package/klp-mcp-server.yaml or create a ToolProvider.');

        $this->assertEquals(Command::SUCCESS, $this->command->execute($this->inputMock, $this->outputMock));
    }

    /**
     * Tests that when valid input is provided, the tool is executed, and the result is displayed.
     */
    public function test_test_tool_valid_input_process_tool(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('getInputSchema')->willReturn([]);
        $toolMock->method('execute')->with([])->willReturn(new TextToolResult('Tool executed successfully'));

        $this->containerMock
            ->method('get')
            ->willReturn($toolMock);

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn(['hello-world' => $toolMock]);

        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn(HelloWorldTool::class);

        $this->ioMock
            ->expects($this->once())
            ->method('success')
            ->with('Tool executed successfully!');

        $this->assertEquals(Command::SUCCESS, $this->command->execute($this->inputMock, $this->outputMock));
    }

    /**
     * Tests that when the tool execution fails, the error message and stack trace are displayed.
     */
    public function test_test_tool_execution_failure_handles_error(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('getInputSchema')->willReturn([]);
        $toolMock->method('execute')->willThrowException(new \RuntimeException('Execution error.'));

        $this->containerMock
            ->method('get')
            ->willReturn($toolMock);

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn(['hello-world' => $toolMock]);

        $this->inputMock
            ->method('getArgument')
            ->with('tool')
            ->willReturn(HelloWorldTool::class);

        $this->ioMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error executing tool'));

        $this->assertEquals(Command::FAILURE, $this->command->execute($this->inputMock, $this->outputMock));
    }

    /**
     * Tests that when valid tools are present, they are displayed in a table.
     */
    public function test_list_all_tools_when_valid_tools_present_displays_table(): void
    {
        $this->inputMock
            ->expects($this->once())
            ->method('getOption')
            ->with('list')
            ->willReturn('list');

        $tool1 = $this->createConfiguredMock(StreamableToolInterface::class, ['getName' => 'Tool1', 'getDescription' => 'This is tool 1']);
        $tool2 = $this->createConfiguredMock(StreamableToolInterface::class, ['getName' => 'Tool2', 'getDescription' => 'This is tool 2']);

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([
                'Tool1' => $tool1,
                'Tool2' => $tool2
            ]);

        $toolMocks = [
            ['name' => 'Tool1', 'class' => get_class($tool1), 'description' => 'This is tool 1'],
            ['name' => 'Tool2', 'class' => get_class($tool2), 'description' => 'This is tool 2'],
        ];

        $this->ioMock
            ->expects($this->once())
            ->method('table')
            ->with(['Name', 'Class', 'Description'], $toolMocks);

        $this->assertEquals(Command::SUCCESS, $this->command->execute($this->inputMock, $this->outputMock));
    }

    /**
     * Tests that when a tool class cannot be loaded, it is gracefully handled.
     */
    public function test_list_all_tools_handles_tool_loading_exceptions_gracefully(): void
    {
        $this->inputMock
            ->expects($this->once())
            ->method('getOption')
            ->with('list')
            ->willReturn('list');
        $tools = [HelloWorldTool::class];

        $tool = $this->createMock(StreamableToolInterface::class);
        $tool->method('getName')->will($this->throwException(new \RuntimeException('Tool not loadable.')));

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([
                'hello-world' => $tool
            ]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains("Couldn't load tool:"));

        $this->assertEquals(Command::SUCCESS, $this->command->execute($this->inputMock, $this->outputMock));
    }

    /**
     * Tests that askForInputData processes a schema with a simple string property.
     */
    public function test_ask_for_input_data_processes_simple_string(): void
    {
        $schema = [
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Enter your name',
                ],
            ],
        ];
        $this->ioMock
            ->method('ask')
            ->with('Value')
            ->willReturn('Test User');

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['name' => 'Test User'], $result);
    }

    /**
     * Tests that askForInputData handles a required property skipped by the user.
     */
    public function test_ask_for_input_data_handles_required_property_skipped(): void
    {
        $schema = [
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Enter your name',
                ],
            ],
            'required' => ['name'],
        ];
        $this->ioMock
            ->method('ask')
            ->with('Value')
            ->willReturn('');

        $this->ioMock
            ->expects($this->exactly(2))
            ->method('warning')
            ->willReturnCallback(function ($message) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertSame('The getInputSchema() method should return an instance of StructuredSchema. Using array is deprecated and will be removed in a future version.', $message);
                } elseif ($callCount === 2) {
                    $this->assertSame('Required field skipped. Using empty string.', $message);
                }
            });

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['name' => ''], $result);
    }

    /**
     * Tests that askForInputData processes a schema with an array type property.
     */
    public function test_ask_for_input_data_processes_array_property(): void
    {
        $schema = [
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'description' => 'Enter tags',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
        $this->ioMock
            ->method('ask')
            ->with('JSON')
            ->willReturn('["tag1", "tag2"]');

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['tags' => ['tag1', 'tag2']], $result);
    }

    /**
     * Tests that askForInputData processes a property of type object with valid JSON input.
     */
    public function test_ask_for_input_data_processes_object_property_valid_json(): void
    {
        $schema = [
            'properties' => [
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Enter metadata',
                    'properties' => [
                        'key' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
        $this->ioMock
            ->method('ask')
            ->with('JSON')
            ->willReturn('{"key": "value"}');

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['metadata' => ['key' => 'value']], $result);
    }

    /**
     * Tests that askForInputData processes a property of type object with valid JSON input.
     */
    public function test_ask_for_input_data_processes_object_property_missing_required(): void
    {
        $schema = [
            'properties' => [
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Enter metadata',
                    'properties' => [
                        'key' => ['type' => 'string'],
                    ],
                ],
            ],
            'required' => ['metadata'],
        ];
        $this->ioMock
            ->method('ask')
            ->with('JSON')
            ->willReturn(null);

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['metadata' => []], $result);
    }

    /**
     * Tests that askForInputData handles invalid JSON for object property.
     */
    public function test_ask_for_input_data_handles_invalid_json_for_object(): void
    {
        $schema = [
            'properties' => [
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Enter metadata',
                ],
            ],
        ];
        $this->ioMock
            ->method('ask')
            ->with('JSON')
            ->willReturn('{invalid json}');

        $this->ioMock
            ->expects($this->once())
            ->method('error')
            ->with('Invalid JSON: Syntax error');

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['metadata' => null], $result);
    }

    public function test_ask_for_input_data_handles_empty_schema(): void
    {
        $schema = [];
        $result = $this->command->askForInputData($schema);

        $this->assertEquals([], $result);
    }

    /**
     * Tests that askForInputData handles invalid JSON for object property.
     */
    public function test_ask_for_input_data_processes_boolean_property(): void
    {
        $schema = [
            'properties' => [
                'confirmation' => [
                    'type' => 'boolean',
                    'description' => 'Are you sure?',
                ],
            ],
        ];
        $this->ioMock
            ->method('confirm')
            ->with('Value (yes/no)', false)
            ->willReturn(true);

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['confirmation' => true], $result);
    }

    /**
     * Tests that askForInputData handles invalid JSON for object property.
     */
    public function test_ask_for_input_data_processes_integer_property(): void
    {
        $schema = [
            'properties' => [
                'age' => [
                    'type' => 'integer',
                    'description' => 'How old are you?',
                ],
            ],
        ];
        $this->ioMock
            ->method('ask')
            ->with('Value', false)
            ->willReturn(20);

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['age' => 20], $result);
    }

    /**
     * Tests that askForInputData handles invalid JSON for object property.
     */
    public function test_ask_for_input_data_handle_alpha_for_integer(): void
    {
        $schema = [
            'properties' => [
                'age' => [
                    'type' => 'integer',
                    'description' => 'How old are you?',
                ],
            ],
        ];
        $this->ioMock
            ->method('ask')
            ->with('Value', false)
            ->willReturn('twenty');

        $result = $this->command->askForInputData($schema);

        $this->assertEquals([], $result);
    }

    /**
     * Tests that askForInputData handles invalid JSON for object property.
     */
    public function test_ask_for_input_data_handle_alpha_for_required_integer(): void
    {
        $schema = [
            'properties' => [
                'age' => [
                    'type' => 'integer',
                    'description' => 'How old are you?',
                ],
            ],
            'required' => ['age'],
        ];
        $this->ioMock
            ->method('ask')
            ->with('Value', false)
            ->willReturn('twenty');
        $this->ioMock
            ->expects($this->exactly(2))
            ->method('warning')
            ->willReturnCallback(function ($message) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertSame('The getInputSchema() method should return an instance of StructuredSchema. Using array is deprecated and will be removed in a future version.', $message);
                } elseif ($callCount === 2) {
                    $this->assertSame('Required field skipped. Using 0.', $message);
                }
            });

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['age' => 0], $result);
    }

    /**
     * Tests displayResult method with no notifications and non-streaming tool.
     */
    public function test_display_result_no_notifications_non_streaming(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('isStreaming')->willReturn(false);

        $resultMock = $this->createMock(ToolResultInterface::class);
        $resultMock->method('getSanitizedResult')->willReturn(['status' => 'success']);

        $this->ioMock->expects($this->once())->method('success')->with('Tool executed successfully!');
        $this->ioMock->expects($this->once())->method('text')->with([
            'Result:',
            json_encode(['status' => 'success'], JSON_PRETTY_PRINT),
        ]);
        $this->ioMock->expects($this->never())->method('newLine');
        $this->ioMock->expects($this->never())->method('section');
        $this->ioMock->expects($this->never())->method('warning');

        $this->command->displayResult($resultMock, [], $toolMock);
    }

    /**
     * Tests displayResult method with notifications sent.
     */
    public function test_display_result_with_notifications(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('isStreaming')->willReturn(true);

        $resultMock = $this->createMock(ToolResultInterface::class);
        $resultMock->method('getSanitizedResult')->willReturn(['status' => 'success']);

        $notifications = [
            ['type' => 'progress', 'value' => 50],
            ['type' => 'progress', 'value' => 100],
        ];

        $this->ioMock->expects($this->once())->method('newLine');
        $this->ioMock->expects($this->once())->method('section')->with('Progress Notifications');

        $callCount = 0;
        $this->ioMock->expects($this->exactly(4))->method('text')
            ->willReturnCallback(function ($text) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals('Total notifications sent: 2', $text);
                } elseif ($callCount === 2) {
                    $expected = [
                        'Notification #1:',
                        json_encode(['type' => 'progress', 'value' => 50], JSON_PRETTY_PRINT),
                    ];
                    $this->assertEquals($expected, $text);
                } elseif ($callCount === 3) {
                    $expected = [
                        'Notification #2:',
                        json_encode(['type' => 'progress', 'value' => 100], JSON_PRETTY_PRINT),
                    ];
                    $this->assertEquals($expected, $text);
                } elseif ($callCount === 4) {
                    $expected = [
                        'Result:',
                        json_encode(['status' => 'success'], JSON_PRETTY_PRINT),
                    ];
                    $this->assertEquals($expected, $text);
                }

                return true;
            });
        $this->ioMock->expects($this->once())->method('success')->with('Tool executed successfully!');

        $this->command->displayResult($resultMock, $notifications, $toolMock);
    }

    /**
     * Tests displayResult method with streaming tool but no notifications sent.
     */
    public function test_display_result_streaming_tool_no_notifications(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('isStreaming')->willReturn(true);

        $resultMock = $this->createMock(\KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface::class);
        $resultMock->method('getSanitizedResult')->willReturn(['status' => 'success']);

        $this->ioMock->expects($this->once())->method('success')->with('Tool executed successfully!');
        $this->ioMock->expects($this->once())->method('text')->with([
            'Result:',
            json_encode(['status' => 'success'], JSON_PRETTY_PRINT),
        ]);
        $this->ioMock->expects($this->once())->method('newLine');
        $this->ioMock->expects($this->once())->method('warning')
            ->with('No progress notifications were sent by this streaming tool. Consider adding progress notifications to improve user experience during long-running operations.');

        $this->command->displayResult($resultMock, [], $toolMock);
    }

    /**
     * Tests displayResult method without tool parameter.
     */
    public function test_display_result_without_tool(): void
    {
        $resultMock = $this->createMock(\KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface::class);
        $resultMock->method('getSanitizedResult')->willReturn(['status' => 'success']);

        $this->ioMock->expects($this->once())->method('success')->with('Tool executed successfully!');
        $this->ioMock->expects($this->once())->method('text')->with([
            'Result:',
            json_encode(['status' => 'success'], JSON_PRETTY_PRINT),
        ]);
        $this->ioMock->expects($this->never())->method('warning');

        $this->command->displayResult($resultMock);
    }

    /**
     * Tests array schema display with complex items structure.
     */
    public function test_display_schema_for_array_with_nested_items(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('getName')->willReturn('ComplexArrayTool');
        $toolMock->method('getDescription')->willReturn('A tool with complex array schema.');
        $toolMock->method('getInputSchema')->willReturn([
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'description' => 'An array of complex objects.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);

        $expectedMessages = [
            'Input schema:',
            '- items: array (optional)',
            '  Description: An array of complex objects.',
            '  Items: object',
            '  Item Properties:',
            '    - id: integer (optional)',
            '      Description: ',
            '    - name: string (optional)',
            '      Description: ',
        ];

        $this->ioMock->expects($this->exactly(2))->method('text');
        $this->ioMock->expects($this->once())->method('newLine');

        $this->command->displaySchema($toolMock);
    }

    /**
     * Tests askForTool when no tools are configured.
     */
    public function test_ask_for_tool_no_tools_configured_returns_null(): void
    {
        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No MCP tools are configured. Add tools in config/package/klp-mcp-server.yaml or create a ToolProvider.');

        $result = $this->invokePrivateMethod('askForTool');

        $this->assertNull($result);
    }

    /**
     * Tests askForTool when tools exist but none can be loaded.
     */
    public function test_ask_for_tool_no_valid_tools_returns_null(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('getName')->willThrowException(new \RuntimeException('Tool not loadable'));

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn(['hello-world' => $toolMock]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No valid MCP tools found.');

        $result = $this->invokePrivateMethod('askForTool');

        $this->assertNull($result);
    }

    /**
     * Tests askForTool with valid tools and user selection.
     */
    public function test_ask_for_tool_with_valid_tools_returns_selected(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('getName')->willReturn('TestTool');

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn(['TestTool' => $toolMock]);

        $this->ioMock
            ->expects($this->once())
            ->method('choice')
            ->with('Select a tool to test', ['TestTool ('.get_class($toolMock).')'])
            ->willReturn('TestTool ('.get_class($toolMock).')');

        $result = $this->invokePrivateMethod('askForTool');

        $this->assertEquals('TestTool', $result);
    }

    /**
     * Tests askForJsonInput with valid array input.
     */
    public function test_ask_for_json_input_valid_array(): void
    {
        $this->ioMock
            ->method('text')
            ->with('Enter JSON for object (or leave empty to skip):');
        $this->ioMock
            ->method('ask')
            ->with('JSON')
            ->willReturn('["item1", "item2"]');

        $result = $this->invokePrivateMethod('askForJsonInput', [[], 'array', false]);

        $this->assertEquals(['item1', 'item2'], $result);
    }

    /**
     * Tests askForJsonInput with object when array expected.
     */
    public function test_ask_for_json_input_object_when_array_expected(): void
    {
        $this->ioMock
            ->method('text')
            ->with('Enter JSON for object (or leave empty to skip):');
        $this->ioMock
            ->method('ask')
            ->with('JSON')
            ->willReturn('{"not": "array"}');

        // This should actually succeed since PHP treats objects as arrays
        $result = $this->invokePrivateMethod('askForJsonInput', [[], 'array', false]);

        $this->assertEquals(['not' => 'array'], $result);
    }

    /**
     * Tests askForJsonInput with required field but empty input.
     */
    public function test_ask_for_json_input_required_empty_input(): void
    {
        $this->ioMock
            ->method('text')
            ->with('Enter JSON for object (or leave empty to skip):');
        $this->ioMock
            ->method('ask')
            ->with('JSON')
            ->willReturn('');

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('Required field skipped. Using empty object.');

        $result = $this->invokePrivateMethod('askForJsonInput', [[], 'object', true]);

        $this->assertEquals([], $result);
    }

    /**
     * Tests askForNumericInput with default value.
     */
    public function test_ask_for_numeric_input_with_default(): void
    {
        $this->ioMock
            ->method('ask')
            ->with('Value (default: 42)')
            ->willReturn('');

        $result = $this->invokePrivateMethod('askForNumericInput', [42, 'integer', false]);

        $this->assertEquals(42, $result);
    }

    /**
     * Tests askForNumericInput with valid float.
     */
    public function test_ask_for_numeric_input_valid_float(): void
    {
        $this->ioMock
            ->method('ask')
            ->with('Value')
            ->willReturn('3.14');

        $result = $this->invokePrivateMethod('askForNumericInput', ['', 'number', false]);

        $this->assertEquals(3.14, $result);
    }

    /**
     * Tests askForNumericInput with non-numeric required field.
     */
    public function test_ask_for_numeric_input_non_numeric_required(): void
    {
        $this->ioMock
            ->method('ask')
            ->with('Value')
            ->willReturn('not-a-number');

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('Required field skipped. Using 0.');

        $result = $this->invokePrivateMethod('askForNumericInput', ['', 'integer', true]);

        $this->assertEquals(0, $result);
    }

    /**
     * Tests askForStandardInput with default value.
     */
    public function test_ask_for_standard_input_with_default(): void
    {
        $this->ioMock
            ->method('ask')
            ->with('Value (default: hello)')
            ->willReturn('');

        $result = $this->invokePrivateMethod('askForStandardInput', ['hello', false]);

        $this->assertEquals('hello', $result);
    }

    /**
     * Tests askForStandardInput with user input.
     */
    public function test_ask_for_standard_input_with_user_input(): void
    {
        $this->ioMock
            ->method('ask')
            ->with('Value')
            ->willReturn('user-input');

        $result = $this->invokePrivateMethod('askForStandardInput', ['', false]);

        $this->assertEquals('user-input', $result);
    }

    /**
     * Tests askForStandardInput with required field but empty input.
     */
    public function test_ask_for_standard_input_required_empty(): void
    {
        $this->ioMock
            ->method('ask')
            ->with('Value')
            ->willReturn('');

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('Required field skipped. Using empty string.');

        $result = $this->invokePrivateMethod('askForStandardInput', ['', true]);

        $this->assertEquals('', $result);
    }

    /**
     * Tests askForInputData with number type property.
     */
    public function test_ask_for_input_data_processes_number_property(): void
    {
        $schema = [
            'properties' => [
                'price' => [
                    'type' => 'number',
                    'description' => 'Enter price',
                ],
            ],
        ];
        $this->ioMock
            ->method('ask')
            ->with('Value', false)
            ->willReturn('19.99');

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['price' => 19.99], $result);
    }

    /**
     * Tests askForInputData with boolean default value.
     */
    public function test_ask_for_input_data_processes_boolean_with_default(): void
    {
        $schema = [
            'properties' => [
                'enabled' => [
                    'type' => 'boolean',
                    'description' => 'Is enabled?',
                    'default' => true,
                ],
            ],
        ];
        $this->ioMock
            ->method('confirm')
            ->with('Value (yes/no)', true)
            ->willReturn(false);

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['enabled' => false], $result);
    }

    /**
     * Tests getInputDataFromOption with TestMcpToolCommandException.
     */
    public function test_get_input_data_from_option_with_exception(): void
    {
        $this->inputMock
            ->method('getOption')
            ->with('input')
            ->willReturn('{"invalid": json}');

        $this->ioMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Invalid JSON input:'));

        $result = $this->invokePrivateMethod('getInputDataFromOption');

        $this->assertNull($result);
    }

    /**
     * Tests listAllTools with class_exists returning false.
     */
    public function test_list_all_tools_class_does_not_exist(): void
    {
        $this->inputMock
            ->expects($this->once())
            ->method('getOption')
            ->with('list')
            ->willReturn('list');

        $this->toolRepositoryMock
            ->method('getTools')
            ->willReturn([]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No MCP tools are configured. Add tools in config/package/klp-mcp-server.yaml or create a ToolProvider.');

        $this->assertEquals(Command::SUCCESS, $this->command->execute($this->inputMock, $this->outputMock));
    }
}
