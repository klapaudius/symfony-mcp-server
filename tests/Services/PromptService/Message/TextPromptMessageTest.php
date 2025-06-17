<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService\Message;

use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class TextPromptMessageTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $message = new TextPromptMessage('user', 'Hello World');
        
        $this->assertInstanceOf(PromptMessageInterface::class, $message);
    }

    public function test_get_sanitized_message_returns_correct_structure_without_arguments(): void
    {
        $message = new TextPromptMessage('user', 'Hello World');
        $result = $message->getSanitizedMessage();

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello World'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_returns_correct_structure_with_assistant_role(): void
    {
        $message = new TextPromptMessage('assistant', 'How can I help you?');
        $result = $message->getSanitizedMessage();

        $expected = [
            'role' => 'assistant',
            'content' => [
                'type' => 'text',
                'text' => 'How can I help you?'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_substitutes_single_argument(): void
    {
        $message = new TextPromptMessage('user', 'Hello {{name}}!');
        $result = $message->getSanitizedMessage(['name' => 'Alice']);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello Alice!'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_substitutes_multiple_arguments(): void
    {
        $message = new TextPromptMessage('user', 'Hello {{name}}, you have {{count}} messages');
        $result = $message->getSanitizedMessage(['name' => 'Bob', 'count' => '5']);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello Bob, you have 5 messages'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_leaves_unmatched_placeholders(): void
    {
        $message = new TextPromptMessage('user', 'Hello {{name}}, you have {{count}} messages');
        $result = $message->getSanitizedMessage(['name' => 'Carol']);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello Carol, you have {{count}} messages'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_ignores_extra_arguments(): void
    {
        $message = new TextPromptMessage('user', 'Hello {{name}}!');
        $result = $message->getSanitizedMessage([
            'name' => 'Dave',
            'extra' => 'ignored',
            'another' => 'also ignored'
        ]);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello Dave!'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_handles_empty_argument_value(): void
    {
        $message = new TextPromptMessage('user', 'Hello {{name}}!');
        $result = $message->getSanitizedMessage(['name' => '']);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello !'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_handles_null_argument_value(): void
    {
        $message = new TextPromptMessage('user', 'Hello {{name}}!');
        $result = $message->getSanitizedMessage(['name' => null]);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello !'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_handles_numeric_argument_values(): void
    {
        $message = new TextPromptMessage('user', 'You have {{count}} items at ${{price}} each');
        $result = $message->getSanitizedMessage(['count' => 42, 'price' => 9.99]);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'You have 42 items at $9.99 each'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_handles_repeated_placeholders(): void
    {
        $message = new TextPromptMessage('user', '{{name}} said "Hello {{name}}"');
        $result = $message->getSanitizedMessage(['name' => 'Eve']);

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Eve said "Hello Eve"'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_get_sanitized_message_with_no_placeholders_and_no_arguments(): void
    {
        $message = new TextPromptMessage('user', 'Static message');
        $result = $message->getSanitizedMessage();

        $expected = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Static message'
            ]
        ];

        $this->assertEquals($expected, $result);
    }
}