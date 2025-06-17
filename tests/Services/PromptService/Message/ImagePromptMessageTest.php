<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService\Message;

use KLP\KlpMcpServer\Services\PromptService\Message\ImagePromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ImagePromptMessageTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $mimeType = 'image/png';
        
        $message = new ImagePromptMessage($base64Data, $mimeType);
        
        $this->assertInstanceOf(PromptMessageInterface::class, $message);
    }

    public function test_get_sanitized_message_returns_correct_structure(): void
    {
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $mimeType = 'image/png';
        
        $message = new ImagePromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('content', $result);
        
        $content = $result['content'];
        $this->assertArrayHasKey('type', $content);
        $this->assertEquals('image', $content['type']);
        $this->assertArrayHasKey('data', $content);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertArrayHasKey('mimeType', $content);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_jpeg_image(): void
    {
        $base64Data = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/wA8=';
        $mimeType = 'image/jpeg';
        
        $message = new ImagePromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('image', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_gif_image(): void
    {
        $base64Data = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $mimeType = 'image/gif';
        
        $message = new ImagePromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('image', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_ignores_arguments(): void
    {
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $mimeType = 'image/png';
        
        $message = new ImagePromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage(['name' => 'test', 'value' => 'ignored']);

        $content = $result['content'];
        $this->assertEquals('image', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_empty_base64_data(): void
    {
        $base64Data = '';
        $mimeType = 'image/png';
        
        $message = new ImagePromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('image', $content['type']);
        $this->assertEquals('', $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_custom_mime_type(): void
    {
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $mimeType = 'image/webp';
        
        $message = new ImagePromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('image', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }
}