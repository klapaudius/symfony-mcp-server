<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService\Message;

use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\ResourcePromptMessage;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ResourcePromptMessageTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $uri = 'file:///path/to/resource.txt';
        $mimeType = 'text/plain';
        $value = 'This is a text resource content';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        
        $this->assertInstanceOf(PromptMessageInterface::class, $message);
    }

    public function test_get_sanitized_message_returns_correct_structure(): void
    {
        $uri = 'file:///path/to/resource.txt';
        $mimeType = 'text/plain';
        $value = 'This is a text resource content';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('content', $result);
        
        $content = $result['content'];
        $this->assertArrayHasKey('type', $content);
        $this->assertEquals('resource', $content['type']);
        $this->assertArrayHasKey('resource', $content);
        
        $resource = $content['resource'];
        $this->assertArrayHasKey('uri', $resource);
        $this->assertEquals($uri, $resource['uri']);
        $this->assertArrayHasKey('mimeType', $resource);
        $this->assertEquals($mimeType, $resource['mimeType']);
        $this->assertArrayHasKey('text', $resource);
        $this->assertEquals($value, $resource['text']);
    }

    public function test_get_sanitized_message_with_json_resource(): void
    {
        $uri = 'https://api.example.com/data.json';
        $mimeType = 'application/json';
        $value = '{"name": "John", "age": 30}';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals($uri, $resource['uri']);
        $this->assertEquals($mimeType, $resource['mimeType']);
        $this->assertEquals($value, $resource['text']);
    }

    public function test_get_sanitized_message_with_xml_resource(): void
    {
        $uri = 'file:///config/settings.xml';
        $mimeType = 'application/xml';
        $value = '<?xml version="1.0"?><config><setting>value</setting></config>';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals($uri, $resource['uri']);
        $this->assertEquals($mimeType, $resource['mimeType']);
        $this->assertEquals($value, $resource['text']);
    }

    public function test_get_sanitized_message_substitutes_single_argument(): void
    {
        $uri = 'file:///templates/greeting.txt';
        $mimeType = 'text/plain';
        $value = 'Hello {{name}}!';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage(['name' => 'Alice']);

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals($uri, $resource['uri']);
        $this->assertEquals($mimeType, $resource['mimeType']);
        $this->assertEquals('Hello Alice!', $resource['text']);
    }

    public function test_get_sanitized_message_substitutes_multiple_arguments(): void
    {
        $uri = 'file:///templates/message.txt';
        $mimeType = 'text/plain';
        $value = 'Hello {{name}}, you have {{count}} messages';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage(['name' => 'Bob', 'count' => '5']);

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals('Hello Bob, you have 5 messages', $resource['text']);
    }

    public function test_get_sanitized_message_leaves_unmatched_placeholders(): void
    {
        $uri = 'file:///templates/partial.txt';
        $mimeType = 'text/plain';
        $value = 'Hello {{name}}, you have {{count}} messages';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage(['name' => 'Carol']);

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals('Hello Carol, you have {{count}} messages', $resource['text']);
    }

    public function test_get_sanitized_message_ignores_extra_arguments(): void
    {
        $uri = 'file:///templates/simple.txt';
        $mimeType = 'text/plain';
        $value = 'Hello {{name}}!';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage([
            'name' => 'Dave',
            'extra' => 'ignored',
            'another' => 'also ignored'
        ]);

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals('Hello Dave!', $resource['text']);
    }

    public function test_get_sanitized_message_with_empty_values(): void
    {
        $uri = '';
        $mimeType = '';
        $value = '';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals('', $resource['uri']);
        $this->assertEquals('', $resource['mimeType']);
        $this->assertEquals('', $resource['text']);
    }

    public function test_get_sanitized_message_with_http_uri(): void
    {
        $uri = 'http://example.com/resource';
        $mimeType = 'text/html';
        $value = '<html><body>Hello World</body></html>';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals($uri, $resource['uri']);
        $this->assertEquals($mimeType, $resource['mimeType']);
        $this->assertEquals($value, $resource['text']);
    }

    public function test_get_sanitized_message_handles_numeric_arguments(): void
    {
        $uri = 'file:///data/stats.txt';
        $mimeType = 'text/plain';
        $value = 'Count: {{count}}, Price: ${{price}}';
        
        $message = new ResourcePromptMessage($uri, $mimeType, $value);
        $result = $message->getSanitizedMessage(['count' => 42, 'price' => 9.99]);

        $content = $result['content'];
        $resource = $content['resource'];
        $this->assertEquals('Count: 42, Price: $9.99', $resource['text']);
    }
}