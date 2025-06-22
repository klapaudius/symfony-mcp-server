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
