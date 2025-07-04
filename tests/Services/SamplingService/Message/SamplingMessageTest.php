<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService\Message;

use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingMessage;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use PHPUnit\Framework\TestCase;

class SamplingMessageTest extends TestCase
{
    public function testCreateSamplingMessage(): void
    {
        $content = new SamplingContent('text', 'Test message content');
        $message = new SamplingMessage('user', $content);

        $this->assertSame('user', $message->getRole());
        $this->assertSame($content, $message->getContent());
    }

    public function testCreateSamplingMessageWithDifferentRoles(): void
    {
        $content = new SamplingContent('text', 'Assistant response');
        
        // Test with assistant role
        $assistantMessage = new SamplingMessage('assistant', $content);
        $this->assertSame('assistant', $assistantMessage->getRole());
        
        // Test with system role
        $systemMessage = new SamplingMessage('system', $content);
        $this->assertSame('system', $systemMessage->getRole());
        
        // Test with user role
        $userMessage = new SamplingMessage('user', $content);
        $this->assertSame('user', $userMessage->getRole());
    }

    public function testToArray(): void
    {
        $content = new SamplingContent('text', 'Array conversion test');
        $message = new SamplingMessage('assistant', $content);

        $array = $message->toArray();

        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertSame('assistant', $array['role']);
        $this->assertIsArray($array['content']);
        $this->assertSame('text', $array['content']['type']);
        $this->assertSame('Array conversion test', $array['content']['text']);
    }

    public function testToArrayWithImageContent(): void
    {
        $imageData = ['base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='];
        $content = new SamplingContent('image', null, $imageData, 'image/png');
        $message = new SamplingMessage('user', $content);

        $array = $message->toArray();

        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertSame('user', $array['role']);
        $this->assertIsArray($array['content']);
        $this->assertSame('image', $array['content']['type']);
        $this->assertArrayHasKey('data', $array['content']);
        $this->assertSame($imageData, $array['content']['data']);
        $this->assertSame('image/png', $array['content']['mimeType']);
    }

    public function testFromArrayWithTextContent(): void
    {
        $data = [
            'role' => 'system',
            'content' => [
                'type' => 'text',
                'text' => 'System prompt message',
            ],
        ];

        $message = SamplingMessage::fromArray($data);

        $this->assertSame('system', $message->getRole());
        
        $content = $message->getContent();
        $this->assertSame('text', $content->getType());
        $this->assertSame('System prompt message', $content->getText());
        $this->assertNull($content->getData());
        $this->assertNull($content->getMimeType());
    }

    public function testFromArrayWithAudioContent(): void
    {
        $audioData = ['base64' => 'SGVsbG8gV29ybGQ=']; // "Hello World" in base64
        $data = [
            'role' => 'assistant',
            'content' => [
                'type' => 'audio',
                'data' => $audioData,
                'mimeType' => 'audio/mp3',
            ],
        ];

        $message = SamplingMessage::fromArray($data);

        $this->assertSame('assistant', $message->getRole());
        
        $content = $message->getContent();
        $this->assertSame('audio', $content->getType());
        $this->assertNull($content->getText());
        $this->assertSame($audioData, $content->getData());
        $this->assertSame('audio/mp3', $content->getMimeType());
    }

    public function testRoundTripConversion(): void
    {
        $originalData = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => 'Round trip test message',
            ],
        ];

        $message = SamplingMessage::fromArray($originalData);
        $convertedData = $message->toArray();

        $this->assertSame($originalData['role'], $convertedData['role']);
        $this->assertSame($originalData['content']['type'], $convertedData['content']['type']);
        $this->assertSame($originalData['content']['text'], $convertedData['content']['text']);
    }

    public function testMessageWithComplexContent(): void
    {
        // Test with resource content type
        $resourceData = [
            'uri' => 'file:///path/to/resource.txt',
            'mimeType' => 'text/plain',
        ];
        $content = new SamplingContent('resource', null, $resourceData, 'application/json');
        $message = new SamplingMessage('assistant', $content);

        $array = $message->toArray();

        $this->assertSame('assistant', $array['role']);
        $this->assertSame('resource', $array['content']['type']);
        $this->assertSame($resourceData, $array['content']['data']);
        $this->assertSame('application/json', $array['content']['mimeType']);
    }

    public function testDifferentContentTypesIntegration(): void
    {
        // Create messages with different content types
        $textContent = new SamplingContent('text', 'Hello');
        $imageContent = new SamplingContent('image', null, ['base64' => 'abc123'], 'image/jpeg');
        $audioContent = new SamplingContent('audio', null, ['base64' => 'def456'], 'audio/wav');

        $textMessage = new SamplingMessage('user', $textContent);
        $imageMessage = new SamplingMessage('user', $imageContent);
        $audioMessage = new SamplingMessage('assistant', $audioContent);

        // Verify each message maintains its content type correctly
        $this->assertSame('text', $textMessage->getContent()->getType());
        $this->assertSame('image', $imageMessage->getContent()->getType());
        $this->assertSame('audio', $audioMessage->getContent()->getType());

        // Verify serialization preserves content types
        $textArray = $textMessage->toArray();
        $imageArray = $imageMessage->toArray();
        $audioArray = $audioMessage->toArray();

        $this->assertSame('text', $textArray['content']['type']);
        $this->assertSame('image', $imageArray['content']['type']);
        $this->assertSame('audio', $audioArray['content']['type']);
    }
}