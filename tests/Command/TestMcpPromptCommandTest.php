<?php

namespace KLP\KlpMcpServer\Tests\Command;

use KLP\KlpMcpServer\Command\TestMcpPromptCommand;
use KLP\KlpMcpServer\Exceptions\TestMcpPromptCommandException;
use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Small]
class TestMcpPromptCommandTest extends TestCase
{
    private TestMcpPromptCommand $command;

    private PromptRepository|MockObject $promptRepositoryMock;

    private InputInterface|MockObject $inputMock;

    private OutputInterface|MockObject $outputMock;

    private SymfonyStyle|MockObject $ioMock;

    protected function setUp(): void
    {
        $this->promptRepositoryMock = $this->createMock(PromptRepository::class);
        $this->inputMock = $this->createMock(InputInterface::class);
        $this->outputMock = $this->createMock(OutputInterface::class);
        $this->ioMock = $this->createMock(SymfonyStyle::class);

        $this->command = new TestMcpPromptCommand($this->promptRepositoryMock);
        $this->command->setApplication($this->createMock(Application::class));
        $this->injectPrivateProperty($this->command, 'input', $this->inputMock);
        $this->injectPrivateProperty($this->command, 'io', $this->ioMock);
    }

    /**
     * Tests that an exception is thrown when no prompt is provided
     */
    public function test_get_prompt_instance_no_prompt_provided_and_no_prompt_configured_throws_exception(): void
    {
        $this->inputMock
            ->method('getArgument')
            ->with('prompt')
            ->willReturn(null);

        $this->promptRepositoryMock
            ->method('getPrompts')
            ->willReturn([]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No MCP prompts are configured. Add prompts in config/packages/klp_mcp_server.yaml');

        $this->expectException(TestMcpPromptCommandException::class);
        $this->expectExceptionMessage('No prompt specified.');

        $this->command->getPromptInstance();
    }

    /**
     * Tests that a prompt instance is returned when a valid identifier is provided
     */
    public function test_get_prompt_instance_valid_identifier_returns_prompt_instance(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $identifier = 'test-prompt';

        $this->inputMock
            ->expects($this->once())
            ->method('getArgument')
            ->with('prompt')
            ->willReturn($identifier);

        $this->promptRepositoryMock
            ->expects($this->once())
            ->method('getPrompt')
            ->with($identifier)
            ->willReturn($promptMock);

        $this->assertSame($promptMock, $this->command->getPromptInstance());
    }

    /**
     * Tests that an exception is thrown when the prompt is not found
     */
    public function test_get_prompt_instance_prompt_not_found_throws_exception(): void
    {
        $identifier = 'nonexistent-prompt';

        $this->inputMock
            ->method('getArgument')
            ->with('prompt')
            ->willReturn($identifier);

        $this->promptRepositoryMock
            ->method('getPrompt')
            ->with($identifier)
            ->willReturn(null);

        $this->expectException(TestMcpPromptCommandException::class);
        $this->expectExceptionMessage("Prompt 'nonexistent-prompt' not found.");

        $this->command->getPromptInstance();
    }

    /**
     * Tests that the displaySchema method correctly displays the schema for a prompt with no arguments
     */
    public function test_display_schema_for_prompt_with_no_arguments(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $promptMock->method('getName')->willReturn('SimplePrompt');
        $promptMock->method('getDescription')->willReturn('A simple prompt.');
        $promptMock->method('getArguments')->willReturn([]);

        $callCount = 0;
        $this->ioMock
            ->expects($this->exactly(2))
            ->method('text')
            ->with($this->callback(function ($text) use (&$callCount, $promptMock) {
                $callCount++;
                if ($callCount === 1) {
                    return $text === [
                        'Testing prompt: SimplePrompt ('.get_class($promptMock).')',
                        'Description: A simple prompt.',
                    ];
                } elseif ($callCount === 2) {
                    return $text === 'This prompt accepts no arguments.';
                }
                return false;
            }));

        $this->ioMock->expects($this->once())->method('newLine');

        $this->command->displaySchema($promptMock);
    }

    /**
     * Tests that the displaySchema method correctly displays the schema for a prompt with arguments
     */
    public function test_display_schema_for_prompt_with_arguments(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $promptMock->method('getName')->willReturn('ComplexPrompt');
        $promptMock->method('getDescription')->willReturn('A complex prompt.');
        $promptMock->method('getArguments')->willReturn([
            [
                'name' => 'topic',
                'description' => 'The topic to discuss',
                'required' => true,
            ],
            [
                'name' => 'style',
                'description' => 'The writing style',
                'required' => false,
            ],
        ]);

        $expectedTexts = [
            [
                'Testing prompt: ComplexPrompt ('.get_class($promptMock).')',
                'Description: A complex prompt.',
            ],
            'Arguments:',
            [
                '  - topic: (required)',
                '    Description: The topic to discuss',
            ],
            [
                '  - style: (optional)',
                '    Description: The writing style',
            ],
        ];

        $this->ioMock
            ->expects($this->exactly(4))
            ->method('text')
            ->with($this->callback(function ($text) use (&$expectedTexts) {
                $expected = array_shift($expectedTexts);
                return $text === $expected;
            }));

        $this->ioMock->expects($this->once())->method('newLine');

        $this->command->displaySchema($promptMock);
    }

    /**
     * Tests that valid JSON input from the --arguments option is correctly parsed and returned as an array
     */
    public function test_get_arguments_from_option_valid_json_returns_array(): void
    {
        $validJson = '{"topic": "AI", "style": "formal"}';

        $this->inputMock
            ->method('getOption')
            ->with('arguments')
            ->willReturn($validJson);

        $result = $this->command->getArgumentsFromOption();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('topic', $result);
        $this->assertEquals('AI', $result['topic']);
        $this->assertArrayHasKey('style', $result);
        $this->assertEquals('formal', $result['style']);
    }

    /**
     * Tests that invalid JSON input from the --arguments option displays an error message
     */
    public function test_get_arguments_from_option_invalid_json_displays_error(): void
    {
        $invalidJson = '{"topic": "AI"';

        $this->inputMock
            ->method('getOption')
            ->with('arguments')
            ->willReturn($invalidJson);

        $this->ioMock
            ->expects($this->once())
            ->method('error')
            ->with('Invalid JSON arguments: Syntax error');

        $result = $this->command->getArgumentsFromOption();

        $this->assertNull($result);
    }

    /**
     * Tests that when the --arguments option is not provided, the method returns null
     */
    public function test_get_arguments_from_option_empty_option_returns_null(): void
    {
        $this->inputMock
            ->method('getOption')
            ->with('arguments')
            ->willReturn(null);

        $result = $this->command->getArgumentsFromOption();

        $this->assertNull($result);
    }

    /**
     * Tests askForArguments with empty schema returns empty array
     */
    public function test_ask_for_arguments_empty_schema_returns_empty_array(): void
    {
        $result = $this->command->askForArguments([]);

        $this->assertEquals([], $result);
    }

    /**
     * Tests askForArguments with required field
     */
    public function test_ask_for_arguments_with_required_field(): void
    {
        $argumentsSchema = [
            [
                'name' => 'topic',
                'description' => 'The main topic',
                'required' => true,
            ],
        ];

        $this->ioMock
            ->expects($this->once())
            ->method('text')
            ->with('Argument: topic The main topic');

        $this->ioMock
            ->expects($this->once())
            ->method('ask')
            ->with('Value')
            ->willReturn('');

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('Required field skipped. Using empty string.');

        $result = $this->command->askForArguments($argumentsSchema);

        $this->assertEquals(['topic' => ''], $result);
    }

    /**
     * Tests askForArguments with optional field
     */
    public function test_ask_for_arguments_with_optional_field(): void
    {
        $argumentsSchema = [
            [
                'name' => 'style',
                'description' => 'Writing style',
                'required' => false,
            ],
        ];

        $this->ioMock
            ->expects($this->once())
            ->method('text')
            ->with('Argument: style Writing style');

        $this->ioMock
            ->expects($this->once())
            ->method('ask')
            ->with('Value')
            ->willReturn('formal');

        $result = $this->command->askForArguments($argumentsSchema);

        $this->assertEquals(['style' => 'formal'], $result);
    }

    /**
     * Tests listing prompts when no prompts are configured
     */
    public function test_list_all_prompts_when_no_prompts_configured_displays_warning(): void
    {
        $this->inputMock
            ->expects($this->once())
            ->method('getOption')
            ->with('list')
            ->willReturn(true);

        $this->promptRepositoryMock
            ->method('getPrompts')
            ->willReturn([]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No MCP prompts are configured. Add prompts in config/packages/klp_mcp_server.yaml');

        $result = $this->command->execute($this->inputMock, $this->outputMock);

        $this->assertEquals(Command::SUCCESS, $result);
    }

    /**
     * Tests listing prompts when prompts are configured
     */
    public function test_list_all_prompts_displays_table(): void
    {
        $prompt1Mock = $this->createMock(PromptInterface::class);
        $prompt1Mock->method('getName')->willReturn('greeting');
        $prompt1Mock->method('getDescription')->willReturn('A greeting prompt that says hello to the user with their name');
        $prompt1Mock->method('getArguments')->willReturn([['name' => 'user_name', 'required' => true]]);

        $prompt2Mock = $this->createMock(PromptInterface::class);
        $prompt2Mock->method('getName')->willReturn('summary');
        $prompt2Mock->method('getDescription')->willReturn('Summarizes a text');
        $prompt2Mock->method('getArguments')->willReturn([]);

        $this->inputMock
            ->expects($this->once())
            ->method('getOption')
            ->with('list')
            ->willReturn(true);

        $this->promptRepositoryMock
            ->method('getPrompts')
            ->willReturn([$prompt1Mock, $prompt2Mock]);

        $expectedTableData = [
            [
                'name' => 'greeting',
                'class' => get_class($prompt1Mock),
                'description' => 'A greeting prompt that says hello to the user with',
                'arguments' => 1,
            ],
            [
                'name' => 'summary',
                'class' => get_class($prompt2Mock),
                'description' => 'Summarizes a text',
                'arguments' => 0,
            ],
        ];

        $this->ioMock
            ->expects($this->once())
            ->method('info')
            ->with('Available MCP Prompts:');

        $this->ioMock
            ->expects($this->once())
            ->method('table')
            ->with(['Name', 'Class', 'Description', 'Arguments'], $expectedTableData);

        $this->ioMock
            ->expects($this->once())
            ->method('text')
            ->with([
                'To test a specific prompt, run:',
                '    php bin/console mcp:test-prompt [prompt_name]',
                '    php bin/console mcp:test-prompt [prompt_name] --arguments=\'{"name":"value"}\'',
            ]);

        $result = $this->command->execute($this->inputMock, $this->outputMock);

        $this->assertEquals(Command::SUCCESS, $result);
    }

    /**
     * Tests askForPrompt when no prompts are configured
     */
    public function test_ask_for_prompt_no_prompts_returns_null(): void
    {
        $this->promptRepositoryMock
            ->method('getPrompts')
            ->willReturn([]);

        $this->ioMock
            ->expects($this->once())
            ->method('warning')
            ->with('No MCP prompts are configured. Add prompts in config/packages/klp_mcp_server.yaml');

        $result = $this->invokePrivateMethod('askForPrompt');

        $this->assertNull($result);
    }

    /**
     * Tests askForPrompt with user selection
     */
    public function test_ask_for_prompt_with_selection(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $promptMock->method('getName')->willReturn('test-prompt');

        $this->promptRepositoryMock
            ->method('getPrompts')
            ->willReturn([$promptMock]);

        $this->ioMock
            ->expects($this->once())
            ->method('choice')
            ->with('Select a prompt to test', ['test-prompt ('.get_class($promptMock).')'])
            ->willReturn('test-prompt ('.get_class($promptMock).')');

        $result = $this->invokePrivateMethod('askForPrompt');

        $this->assertEquals('test-prompt', $result);
    }

    /**
     * Tests displayResult with text messages
     */
    public function test_display_result_with_text_messages(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $arguments = ['topic' => 'AI'];
        $messages = [
            [
                'role' => PromptMessageInterface::ROLE_USER,
                'content' => [
                    'type' => 'text',
                    'text' => 'Tell me about AI',
                ],
            ],
            [
                'role' => PromptMessageInterface::ROLE_ASSISTANT,
                'content' => [
                    'type' => 'text',
                    'text' => 'AI is artificial intelligence...',
                ],
            ],
        ];

        $this->ioMock->expects($this->once())->method('success')->with('Prompt executed successfully!');
        $sectionCalls = 0;
        $this->ioMock->expects($this->exactly(2))->method('section')
            ->with($this->callback(function ($text) use (&$sectionCalls) {
                $sectionCalls++;
                return $sectionCalls === 1 ? $text === 'Arguments Used' : $text === 'Generated Messages';
            }));
        $this->ioMock->expects($this->exactly(2))->method('newLine');

        $textCalls = 0;
        $this->ioMock->expects($this->exactly(8))->method('text')
            ->with($this->callback(function ($text) use (&$textCalls) {
                $textCalls++;
                switch ($textCalls) {
                    case 1:
                        return $text === json_encode(['topic' => 'AI'], JSON_PRETTY_PRINT);
                    case 2:
                        return $text === 'Total messages: 2';
                    case 3:
                        return $text === ['Message #1:', 'Role: user'];
                    case 4:
                        return $text === 'Type: text';
                    case 5:
                        return $text === ['Content:', 'Tell me about AI'];
                    case 6:
                        return $text === ['Message #2:', 'Role: assistant'];
                    case 7:
                        return $text === 'Type: text';
                    case 8:
                        return $text === ['Content:', 'AI is artificial intelligence...'];
                }
                return false;
            }));

        $this->command->displayResult($promptMock, $arguments, $messages);
    }

    /**
     * Tests displayResult with invalid role
     */
    public function test_display_result_with_invalid_role(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $arguments = [];
        $messages = [
            [
                'role' => 'invalid-role',
                'content' => [
                    'type' => 'text',
                    'text' => 'Some text',
                ],
            ],
        ];

        $this->ioMock->expects($this->once())->method('success')->with('Prompt executed successfully!');
        $this->ioMock->expects($this->once())->method('section')->with('Generated Messages');
        $this->ioMock->expects($this->once())->method('newLine');
        $this->ioMock->expects($this->once())->method('error')
            ->with('Invalid role "invalid-role" detected. Valid roles are: user, assistant');

        $this->command->displayResult($promptMock, $arguments, $messages);
    }

    /**
     * Tests displayResult with image message
     */
    public function test_display_result_with_image_message(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $arguments = [];
        $messages = [
            [
                'role' => PromptMessageInterface::ROLE_USER,
                'content' => [
                    'type' => 'image',
                    'data' => 'base64-encoded-image-data',
                    'mimeType' => 'image/png',
                ],
            ],
        ];

        $this->ioMock->expects($this->once())->method('success')->with('Prompt executed successfully!');
        $this->ioMock->expects($this->once())->method('section')->with('Generated Messages');
        $this->ioMock->expects($this->once())->method('newLine');

        $textCalls = 0;
        $this->ioMock->expects($this->exactly(4))->method('text')
            ->with($this->callback(function ($text) use (&$textCalls) {
                $textCalls++;
                switch ($textCalls) {
                    case 1:
                        return $text === 'Total messages: 1';
                    case 2:
                        return $text === ['Message #1:', 'Role: user'];
                    case 3:
                        return $text === 'Type: image';
                    case 4:
                        return $text === ['Image Data: base64-encoded-image-data', 'MIME Type: image/png'];
                }
                return false;
            }));

        $this->command->displayResult($promptMock, $arguments, $messages);
    }

    /**
     * Tests displayResult with resource message
     */
    public function test_display_result_with_resource_message(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $arguments = [];
        $messages = [
            [
                'role' => PromptMessageInterface::ROLE_ASSISTANT,
                'content' => [
                    'type' => 'resource',
                    'resource' => [
                        'uri' => 'file:///path/to/resource.txt',
                        'text' => 'Resource content',
                        'mimeType' => 'text/plain',
                    ],
                ],
            ],
        ];

        $this->ioMock->expects($this->once())->method('success')->with('Prompt executed successfully!');
        $this->ioMock->expects($this->once())->method('section')->with('Generated Messages');
        $this->ioMock->expects($this->once())->method('newLine');

        $textCalls = 0;
        $this->ioMock->expects($this->exactly(4))->method('text')
            ->with($this->callback(function ($text) use (&$textCalls) {
                $textCalls++;
                switch ($textCalls) {
                    case 1:
                        return $text === 'Total messages: 1';
                    case 2:
                        return $text === ['Message #1:', 'Role: assistant'];
                    case 3:
                        return $text === 'Type: resource';
                    case 4:
                        return $text === ['Resource URI: file:///path/to/resource.txt', 'Text: Resource content', 'MIME Type: text/plain'];
                }
                return false;
            }));

        $this->command->displayResult($promptMock, $arguments, $messages);
    }

    /**
     * Tests displayResult with malformed message (fallback to raw display)
     */
    public function test_display_result_with_malformed_message(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $arguments = [];
        $messages = [
            [
                'role' => PromptMessageInterface::ROLE_USER,
                // Missing content key
            ],
        ];

        $this->ioMock->expects($this->once())->method('success')->with('Prompt executed successfully!');
        $this->ioMock->expects($this->once())->method('section')->with('Generated Messages');
        $this->ioMock->expects($this->once())->method('newLine');

        $textCalls = 0;
        $this->ioMock->expects($this->exactly(3))->method('text')
            ->with($this->callback(function ($text) use (&$textCalls) {
                $textCalls++;
                switch ($textCalls) {
                    case 1:
                        return $text === 'Total messages: 1';
                    case 2:
                        return $text === ['Message #1:', 'Role: user'];
                    case 3:
                        return $text === ['Raw message:', json_encode(['role' => 'user'], JSON_PRETTY_PRINT)];
                }
                return false;
            }));

        $this->command->displayResult($promptMock, $arguments, $messages);
    }

    /**
     * Tests successful prompt execution
     */
    public function test_test_prompt_success(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $promptMock->method('getName')->willReturn('test-prompt');
        $promptMock->method('getDescription')->willReturn('Test prompt');
        $promptMock->method('getArguments')->willReturn([]);

        $textMessage = new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Hello');
        $collectionMessage = new CollectionPromptMessage();
        $collectionMessage->addMessage($textMessage);

        $promptMock->method('getMessages')->willReturn($collectionMessage);

        $this->inputMock->method('getArgument')->with('prompt')->willReturn('test-prompt');
        $this->inputMock->method('getOption')->willReturn(null);

        $this->promptRepositoryMock->method('getPrompt')->with('test-prompt')->willReturn($promptMock);

        $this->ioMock->expects($this->once())->method('success')->with('Prompt executed successfully!');

        $result = $this->command->execute($this->inputMock, $this->outputMock);

        $this->assertEquals(Command::SUCCESS, $result);
    }

    /**
     * Tests prompt execution with exception
     */
    public function test_test_prompt_execution_failure(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $promptMock->method('getName')->willReturn('test-prompt');
        $promptMock->method('getDescription')->willReturn('Test prompt');
        $promptMock->method('getArguments')->willReturn([]);
        $promptMock->method('getMessages')->willThrowException(new \RuntimeException('Execution error'));

        $this->inputMock->method('getArgument')->with('prompt')->willReturn('test-prompt');
        $this->inputMock->method('getOption')->willReturn(null);

        $this->promptRepositoryMock->method('getPrompt')->with('test-prompt')->willReturn($promptMock);

        // Update expectations - displaySchema will be called first
        $textCallCount = 0;
        $this->ioMock->expects($this->exactly(4))->method('text')
            ->with($this->callback(function ($text) use (&$textCallCount) {
                $textCallCount++;
                if ($textCallCount === 1) {
                    // First call from displaySchema
                    return is_array($text) && 
                           str_starts_with($text[0], 'Testing prompt: test-prompt (') &&
                           $text[1] === 'Description: Test prompt';
                } elseif ($textCallCount === 2) {
                    // Second call from displaySchema (no arguments)
                    return $text === 'This prompt accepts no arguments.';
                } elseif ($textCallCount === 3) {
                    // Third call - executing prompt with arguments
                    return is_array($text) && 
                           $text[0] === 'Executing prompt with arguments:' &&
                           $text[1] === '[]';
                } elseif ($textCallCount === 4) {
                    // Fourth call from error stack trace
                    return is_array($text) && $text[0] === 'Stack trace:' && !empty($text[1]);
                }
                return false;
            }));
        $this->ioMock->expects($this->once())->method('newLine');
        $this->ioMock->expects($this->once())->method('error')->with('Error executing prompt: Execution error');

        $result = $this->command->execute($this->inputMock, $this->outputMock);

        $this->assertEquals(Command::FAILURE, $result);
    }

    /**
     * Tests that getPromptInstance asks for prompt when none provided
     */
    public function test_get_prompt_instance_asks_for_prompt_when_none_provided(): void
    {
        $promptMock = $this->createMock(PromptInterface::class);
        $promptMock->method('getName')->willReturn('interactive-prompt');

        $this->inputMock->method('getArgument')->with('prompt')->willReturn(null);

        $this->promptRepositoryMock->method('getPrompts')->willReturn([$promptMock]);
        $this->promptRepositoryMock->method('getPrompt')->with('interactive-prompt')->willReturn($promptMock);

        $this->ioMock->expects($this->once())->method('choice')
            ->with('Select a prompt to test', ['interactive-prompt ('.get_class($promptMock).')'])
            ->willReturn('interactive-prompt ('.get_class($promptMock).')');

        $result = $this->command->getPromptInstance();

        $this->assertSame($promptMock, $result);
    }

    /**
     * Tests test prompt with invalid arguments from command line
     */
    public function test_test_prompt_with_invalid_arguments_from_option(): void
    {
        $this->inputMock->method('getArgument')->with('prompt')->willReturn('test-prompt');
        $this->inputMock->method('getOption')->willReturnMap([
            ['list', false], // First call is for --list
            ['arguments', 'invalid-json'], // Second call is for --arguments
        ]);

        $promptMock = $this->createMock(PromptInterface::class);
        $promptMock->method('getName')->willReturn('test-prompt');
        $promptMock->method('getDescription')->willReturn('Test prompt');
        $promptMock->method('getArguments')->willReturn([
            [
                'name' => 'topic',
                'description' => 'Writing topic',
                'required' => true,
            ],
        ]);
        
        $this->promptRepositoryMock->method('getPrompt')->willReturn($promptMock);

        // Instead of creating an actual CollectionPromptMessage,
        // make the prompt mock throw an exception when getMessages is called
        $promptMock->method('getMessages')
            ->willThrowException(new \RuntimeException('Test error during prompt execution'));

        // This test expects the command to fail when invalid JSON is provided
        // and then fails during prompt execution
        $errorCalls = 0;
        $this->ioMock->expects($this->exactly(2))->method('error')
            ->with($this->callback(function ($text) use (&$errorCalls) {
                $errorCalls++;
                if ($errorCalls === 1) {
                    return $text === 'Invalid JSON arguments: Syntax error';
                } elseif ($errorCalls === 2) {
                    return $text === 'Error executing prompt: Test error during prompt execution';
                }
                return false;
            }));
        
        // Use willReturnCallback to handle multiple text() calls
        $this->ioMock->method('text');
        $this->ioMock->method('newLine');
        
        // When askForArguments is called, user provides empty value for required field
        $this->ioMock->expects($this->once())->method('ask')->with('Value')->willReturn('');
        $this->ioMock->expects($this->once())->method('warning')->with('Required field skipped. Using empty string.');
        
        $this->ioMock->method('section');

        $result = $this->command->execute($this->inputMock, $this->outputMock);

        // The command should fail due to the exception during prompt execution
        $this->assertEquals(Command::FAILURE, $result);
    }

    /**
     * Helper method to invoke private methods on the command class
     */
    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod(TestMcpPromptCommand::class, $methodName);

        return $reflection->invokeArgs($this->command, $args);
    }

    /**
     * Helper method to inject private properties
     */
    private function injectPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $propertyName);
        $reflection->setValue($object, $value);
    }
}
