<?php

namespace KLP\KlpMcpServer\Tests\Command;

use KLP\KlpMcpServer\Command\TestMcpToolCommand;
use KLP\KlpMcpServer\Exceptions\TestMcpToolCommandException;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
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
        $this->containerMock
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn([HelloWorldTool::class]);
        $this->containerMock
            ->method('get')
            ->with(HelloWorldTool::class)
            ->willReturn(new HelloWorldTool);
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
        $configuredTools = ['App\\Tools\\CustomTool'];
        $toolMock = $this->createMock(StreamableToolInterface::class);

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
                    $toolMock = $this->createMock(StreamableToolInterface::class);
                    $toolMock->method('getName')->willReturn('Valid Tool');

                    return $toolMock;
                }

                return null;
            });

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

        $this->containerMock
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn([]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No MCP tools are configured. Add tools in config/package/klp-mcp-server.yaml');

        $this->assertEquals(Command::SUCCESS, $this->command->execute($this->inputMock, $this->outputMock));
    }

    /**
     * Tests that when valid input is provided, the tool is executed, and the result is displayed.
     */
    public function test_test_tool_valid_input_process_tool(): void
    {
        $toolMock = $this->createMock(StreamableToolInterface::class);
        $toolMock->method('getInputSchema')->willReturn([]);
        $toolMock->method('execute')->with([])->willReturn(['success' => true]);

        $this->containerMock
            ->method('get')
            ->willReturn($toolMock);

        $this->containerMock
            ->method('getParameter')
            ->willReturn([HelloWorldTool::class]);

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

        $this->containerMock
            ->method('getParameter')
            ->willReturn([HelloWorldTool::class]);

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
        $tools = [HelloWorldTool::class, VersionCheckTool::class];
        $toolMocks = [
            ['name' => 'Tool1', 'class' => HelloWorldTool::class, 'description' => 'This is tool 1'],
            ['name' => 'Tool2', 'class' => VersionCheckTool::class, 'description' => 'This is tool 2'],
        ];

        $this->containerMock
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn($tools);

        $this->containerMock
            ->method('get')
            ->willReturnMap([
                [HelloWorldTool::class, $this->createConfiguredMock(StreamableToolInterface::class, ['getName' => 'Tool1', 'getDescription' => 'This is tool 1'])],
                [VersionCheckTool::class, $this->createConfiguredMock(StreamableToolInterface::class, ['getName' => 'Tool2', 'getDescription' => 'This is tool 2'])],
            ]);

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

        $this->containerMock
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn($tools);

        $this->containerMock
            ->method('get')
            ->will($this->throwException(new \RuntimeException('Tool not loadable.')));

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with("Couldn't load tool class: ".HelloWorldTool::class);

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
            ->expects($this->once())
            ->method('warning')
            ->with('Required field skipped. Using empty string.');

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
            ->expects($this->once())
            ->method('warning')
            ->with('Required field skipped. Using 0.');

        $result = $this->command->askForInputData($schema);

        $this->assertEquals(['age' => 0], $result);
    }
}
