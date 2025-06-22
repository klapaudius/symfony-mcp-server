<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService\Message;

use KLP\KlpMcpServer\Services\PromptService\Message\AudioPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\ImagePromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\ResourcePromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class CollectionPromptMessageTest extends TestCase
{
    public function test_constructor_creates_empty_collection(): void
    {
        $collection = new CollectionPromptMessage();

        $result = $collection->getSanitizedMessages();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_add_message_adds_text_message(): void
    {
        $collection = new CollectionPromptMessage();
        $textMessage = new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Hello World');

        $result = $collection->addMessage($textMessage);

        $this->assertSame($collection, $result); // Test fluent interface

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals([
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Hello World'
            ]
        ], $messages[0]);
    }

    public function test_add_message_adds_multiple_messages(): void
    {
        $collection = new CollectionPromptMessage();
        $textMessage1 = new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'First message');
        $textMessage2 = new TextPromptMessage(PromptMessageInterface::ROLE_ASSISTANT, 'Second message');

        $collection->addMessage($textMessage1)->addMessage($textMessage2);

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(2, $messages);

        $this->assertEquals([
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'First message'
            ]
        ], $messages[0]);

        $this->assertEquals([
            'role' => 'assistant',
            'content' => [
                'type' => 'text',
                'text' => 'Second message'
            ]
        ], $messages[1]);
    }

    public function test_add_message_adds_image_message(): void
    {
        $collection = new CollectionPromptMessage();
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $imageMessage = new ImagePromptMessage($base64Data, 'image/png');

        $collection->addMessage($imageMessage);

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(1, $messages);

        $content = $messages[0]['content'];
        $this->assertEquals('image', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals('image/png', $content['mimeType']);
    }

    public function test_add_message_adds_audio_message(): void
    {
        $collection = new CollectionPromptMessage();
        $base64Data = 'UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoAAAC+f+f+rgAAA==';
        $audioMessage = new AudioPromptMessage($base64Data, 'audio/wav');

        $collection->addMessage($audioMessage);

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(1, $messages);

        $content = $messages[0]['content'];
        $this->assertEquals('audio', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals('audio/wav', $content['mimeType']);
    }

    public function test_add_message_adds_resource_message(): void
    {
        $collection = new CollectionPromptMessage();
        $uri = 'file:///path/to/resource.txt';
        $mimeType = 'text/plain';
        $value = 'Resource content';
        $resourceMessage = new ResourcePromptMessage($uri, $mimeType, $value);

        $collection->addMessage($resourceMessage);

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(1, $messages);

        $content = $messages[0]['content'];
        $this->assertEquals('resource', $content['type']);
        $this->assertArrayHasKey('resource', $content);

        $resource = $content['resource'];
        $this->assertEquals($uri, $resource['uri']);
        $this->assertEquals($mimeType, $resource['mimeType']);
        $this->assertEquals($value, $resource['text']);
    }

    public function test_get_sanitized_messages_with_mixed_message_types(): void
    {
        $collection = new CollectionPromptMessage();

        $textMessage = new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Here is the data:');
        $resourceMessage = new ResourcePromptMessage('file:///data.json', 'application/json', '{"status": "ok"}');
        $imageMessage = new ImagePromptMessage('base64data', 'image/png');
        $audioMessage = new AudioPromptMessage('audiodata', 'audio/wav');

        $collection
            ->addMessage($textMessage)
            ->addMessage($resourceMessage)
            ->addMessage($imageMessage)
            ->addMessage($audioMessage);

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(4, $messages);

        // Verify each message type
        $this->assertEquals('text', $messages[0]['content']['type']);
        $this->assertEquals('resource', $messages[1]['content']['type']);
        $this->assertEquals('image', $messages[2]['content']['type']);
        $this->assertEquals('audio', $messages[3]['content']['type']);
    }

    public function test_get_sanitized_messages_preserves_order(): void
    {
        $collection = new CollectionPromptMessage();

        for ($i = 1; $i <= 5; $i++) {
            $collection->addMessage(new TextPromptMessage(PromptMessageInterface::ROLE_USER, "Message {$i}"));
        }

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(5, $messages);

        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals("Message " . ($i + 1), $messages[$i]['content']['text']);
        }
    }

    public function test_get_sanitized_messages_handles_empty_arguments(): void
    {
        $collection = new CollectionPromptMessage();
        $textMessage = new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Hello {{name}}!');

        $collection->addMessage($textMessage);

        $messages = $collection->getSanitizedMessages([]);
        $this->assertCount(1, $messages);
        $this->assertEquals('Hello {{name}}!', $messages[0]['content']['text']);
    }

    public function test_fluent_interface_chaining(): void
    {
        $collection = new CollectionPromptMessage();

        $result = $collection
            ->addMessage(new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'First'))
            ->addMessage(new TextPromptMessage(PromptMessageInterface::ROLE_ASSISTANT, 'Second'))
            ->addMessage(new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Third'));

        $this->assertSame($collection, $result);

        $messages = $collection->getSanitizedMessages();
        $this->assertCount(3, $messages);
    }
}
