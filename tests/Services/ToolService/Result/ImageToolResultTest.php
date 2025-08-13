<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Result;

use KLP\KlpMcpServer\Services\ToolService\Result\ImageToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ImageToolResultTest extends TestCase
{
    public function test_constructor_sets_correct_properties(): void
    {
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $mimeType = 'image/png';

        $result = new ImageToolResult($base64Data, $mimeType);

        $this->assertInstanceOf(ImageToolResult::class, $result);
    }

    public function test_get_sanitized_result_returns_correct_format(): void
    {
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $mimeType = 'image/png';

        $result = new ImageToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertArrayHasKey('type', $sanitizedResult);
        $this->assertArrayHasKey('data', $sanitizedResult);
        $this->assertArrayHasKey('mimeType', $sanitizedResult);
        $this->assertEquals('image', $sanitizedResult['type']);
        $this->assertEquals($base64Data, $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_handles_jpeg_mime_type(): void
    {
        $base64Data = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQ==';
        $mimeType = 'image/jpeg';

        $result = new ImageToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('image', $sanitizedResult['type']);
        $this->assertEquals($base64Data, $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_handles_gif_mime_type(): void
    {
        $base64Data = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $mimeType = 'image/gif';

        $result = new ImageToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('image', $sanitizedResult['type']);
        $this->assertEquals($base64Data, $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_handles_empty_base64_data(): void
    {
        $base64Data = '';
        $mimeType = 'image/png';

        $result = new ImageToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('image', $sanitizedResult['type']);
        $this->assertEquals('', $sanitizedResult['data']);
        $this->assertEquals($mimeType, $sanitizedResult['mimeType']);
    }

    public function test_get_sanitized_result_preserves_exact_data(): void
    {
        $base64Data = 'VGVzdCBkYXRhIGZvciBpbWFnZQ==';
        $mimeType = 'image/webp';

        $result = new ImageToolResult($base64Data, $mimeType);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertSame($base64Data, $sanitizedResult['data']);
        $this->assertSame($mimeType, $sanitizedResult['mimeType']);
    }
}
