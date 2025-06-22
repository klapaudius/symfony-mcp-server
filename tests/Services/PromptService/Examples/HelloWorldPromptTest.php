<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService\Examples;

use KLP\KlpMcpServer\Services\PromptService\Examples\HelloWorldPrompt;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class HelloWorldPromptTest extends TestCase
{
    private HelloWorldPrompt $prompt;

    protected function setUp(): void
    {
        $this->prompt = new HelloWorldPrompt();
    }

    public function test_get_name_returns_correct_value(): void
    {
        $this->assertEquals('hello-world', $this->prompt->getName());
    }

    public function test_get_description_returns_correct_value(): void
    {
        $this->assertEquals(
            'A simple greeting prompt that can be personalized with a name',
            $this->prompt->getDescription()
        );
    }

    public function test_get_arguments_returns_correct_structure(): void
    {
        $arguments = $this->prompt->getArguments();

        $this->assertIsArray($arguments);
        $this->assertCount(1, $arguments);

        $this->assertArrayHasKey('name', $arguments[0]);
        $this->assertEquals('name', $arguments[0]['name']);

        $this->assertArrayHasKey('description', $arguments[0]);
        $this->assertEquals('The name to include in the greeting', $arguments[0]['description']);

        $this->assertArrayHasKey('required', $arguments[0]);
        $this->assertFalse($arguments[0]['required']);
    }

    public function test_get_messages_returns_default_greeting_without_arguments(): void
    {
        $messages = $this->prompt->getMessages()->getSanitizedMessages();

        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertArrayHasKey('role', $message);
        $this->assertEquals('user', $message['role']);

        $this->assertArrayHasKey('content', $message);
        $this->assertIsArray($message['content']);

        $this->assertArrayHasKey('type', $message['content']);
        $this->assertEquals('text', $message['content']['type']);

        $this->assertArrayHasKey('text', $message['content']);
        $this->assertEquals(
            'Hello, World! This is an example MCP prompt.',
            $message['content']['text']
        );
    }

    public function test_get_messages_returns_personalized_greeting_with_name_argument(): void
    {
        $messages = $this->prompt->getMessages(['name' => 'Alice'])->getSanitizedMessages();

        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertArrayHasKey('role', $message);
        $this->assertEquals('user', $message['role']);

        $this->assertArrayHasKey('content', $message);
        $this->assertIsArray($message['content']);

        $this->assertArrayHasKey('type', $message['content']);
        $this->assertEquals('text', $message['content']['type']);

        $this->assertArrayHasKey('text', $message['content']);
        $this->assertEquals(
            'Hello, Alice! This is an example MCP prompt.',
            $message['content']['text']
        );
    }

    public function test_get_messages_ignores_extra_arguments(): void
    {
        $messages = $this->prompt->getMessages([
            'name' => 'Bob',
            'extra' => 'ignored',
            'another' => 'also ignored'
        ])->getSanitizedMessages();

        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertEquals(
            'Hello, Bob! This is an example MCP prompt.',
            $message['content']['text']
        );
    }

    public function test_get_messages_handles_empty_name(): void
    {
        $messages = $this->prompt->getMessages(['name' => ''])->getSanitizedMessages();

        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertEquals(
            'Hello, ! This is an example MCP prompt.',
            $message['content']['text']
        );
    }

    public function test_get_messages_handles_null_name(): void
    {
        $messages = $this->prompt->getMessages(['name' => null])->getSanitizedMessages();

        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertEquals(
            'Hello, World! This is an example MCP prompt.',
            $message['content']['text']
        );
    }

    public function test_prompt_implements_interface_correctly(): void
    {
        $this->assertInstanceOf(
            \KLP\KlpMcpServer\Services\PromptService\PromptInterface::class,
            $this->prompt
        );
    }
}
