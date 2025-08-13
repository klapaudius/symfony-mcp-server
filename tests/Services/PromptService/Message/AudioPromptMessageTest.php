<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService\Message;

use KLP\KlpMcpServer\Services\PromptService\Message\AudioPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class AudioPromptMessageTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $base64Data = 'UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoAAAC+f+f+rgAAA==';
        $mimeType = 'audio/wav';

        $message = new AudioPromptMessage($base64Data, $mimeType);

        $this->assertInstanceOf(PromptMessageInterface::class, $message);
    }

    public function test_get_sanitized_message_returns_correct_structure(): void
    {
        $base64Data = 'UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoAAAC+f+f+rgAAA==';
        $mimeType = 'audio/wav';

        $message = new AudioPromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('role', $result);
        $this->assertArrayHasKey('content', $result);

        $content = $result['content'];
        $this->assertArrayHasKey('type', $content);
        $this->assertEquals('audio', $content['type']);
        $this->assertArrayHasKey('data', $content);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertArrayHasKey('mimeType', $content);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_mp3_audio(): void
    {
        $base64Data = '//uQRAAAAWMSLwUIYd2VjGBhTlGCxKWvL0VVVVVV4+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+f+';
        $mimeType = 'audio/mpeg';

        $message = new AudioPromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('audio', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_ogg_audio(): void
    {
        $base64Data = 'T2dnUwACAAAAAAAAAAB8AQAAAAAAABaGrCEBHgF2b3JiaXMAAAAAAUAfAAAAAAAAgLsAAAAAAAC4AU9nZ1MAEwAKAAAAAAAAfAEAAAEAAAC7pPKKAQ==';
        $mimeType = 'audio/ogg';

        $message = new AudioPromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('audio', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_ignores_arguments(): void
    {
        $base64Data = 'UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoAAAC+f+f+rgAAA==';
        $mimeType = 'audio/wav';

        $message = new AudioPromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage(['name' => 'test', 'value' => 'ignored']);

        $content = $result['content'];
        $this->assertEquals('audio', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_empty_base64_data(): void
    {
        $base64Data = '';
        $mimeType = 'audio/wav';

        $message = new AudioPromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('audio', $content['type']);
        $this->assertEquals('', $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_aac_audio(): void
    {
        $base64Data = 'AAACAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
        $mimeType = 'audio/aac';

        $message = new AudioPromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('audio', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }

    public function test_get_sanitized_message_with_flac_audio(): void
    {
        $base64Data = 'ZkxhQwAAACIQABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==';
        $mimeType = 'audio/flac';

        $message = new AudioPromptMessage($base64Data, $mimeType);
        $result = $message->getSanitizedMessage();

        $content = $result['content'];
        $this->assertEquals('audio', $content['type']);
        $this->assertEquals($base64Data, $content['data']);
        $this->assertEquals($mimeType, $content['mimeType']);
    }
}
