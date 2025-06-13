<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Result;

use KLP\KlpMcpServer\Services\ToolService\Result\AudioToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class AudioToolResultTest extends TestCase
{
    public function test_constructor_sets_correct_properties(): void
    {
        $base64Data = 'UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAC';
        $mimeType = 'audio/wav';
        
        $result = new AudioToolResult($base64Data, $mimeType);

        $this->assertInstanceOf(AudioToolResult::class, $result);
    }

    public function test_get_sanitized_result_returns_correct_format(): void
    {
        $base64Data = 'UklGRjIAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ4AAAC';
        $mimeType = 'audio/wav';
        
        $result = new AudioToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertArrayHasKey('type', $sanitizedResult);
        $this->assertArrayHasKey('data', $sanitizedResult);
        $this->assertArrayHasKey('mimeType', $sanitizedResult);
        $this->assertEquals('audio', $sanitizedResult['type']);
        $this->assertEquals($base64Data, $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_handles_mp3_mime_type(): void
    {
        $base64Data = 'SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA';
        $mimeType = 'audio/mpeg';
        
        $result = new AudioToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('audio', $sanitizedResult['type']);
        $this->assertEquals($base64Data, $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_handles_ogg_mime_type(): void
    {
        $base64Data = 'T2dnUwACAAAAAAAAAABMQW1lIDMuMTAwAAAAAAAAAAAAAAA';
        $mimeType = 'audio/ogg';
        
        $result = new AudioToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('audio', $sanitizedResult['type']);
        $this->assertEquals($base64Data, $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_handles_aac_mime_type(): void
    {
        $base64Data = 'ZnR5cE00QSAAAAAAaXNvbWlzb21tcDQxAAAAAWlsbWRkYXRh';
        $mimeType = 'audio/aac';
        
        $result = new AudioToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('audio', $sanitizedResult['type']);
        $this->assertEquals($base64Data, $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_handles_empty_base64_data(): void
    {
        $base64Data = '';
        $mimeType = 'audio/wav';
        
        $result = new AudioToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('audio', $sanitizedResult['type']);
        $this->assertEquals('', $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_preserves_exact_data(): void
    {
        $base64Data = 'VGVzdCBhdWRpbyBkYXRh';
        $mimeType = 'audio/flac';
        
        $result = new AudioToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertSame($base64Data, $sanitizedResult['data']);
        $this->assertSame($mimeType, $sanitizedResult['mimeType']);
    }
}