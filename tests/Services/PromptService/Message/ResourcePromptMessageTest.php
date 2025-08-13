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
}
