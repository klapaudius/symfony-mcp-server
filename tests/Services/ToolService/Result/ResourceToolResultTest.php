<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Result;

use KLP\KlpMcpServer\Services\ToolService\Result\ResourceToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ResourceToolResultTest extends TestCase
{
    public function test_constructor_sets_correct_properties(): void
    {
        $uri = 'https://example.com/resource.json';
        $mimeType = 'application/json';
        $value = '{"key": "value"}';
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $this->assertInstanceOf(ResourceToolResult::class, $result);
    }

    public function test_get_sanitized_result_returns_correct_format(): void
    {
        $uri = 'https://example.com/resource.json';
        $mimeType = 'application/json';
        $value = '{"key": "value"}';
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertArrayHasKey('type', $sanitizedResult);
        $this->assertArrayHasKey('resource', $sanitizedResult);
        $this->assertEquals('resource', $sanitizedResult['type']);
        $this->assertIsArray($sanitizedResult['resource']);
        $this->assertArrayHasKey('uri', $sanitizedResult['resource']);
        $this->assertArrayHasKey('mimeType', $sanitizedResult['resource']);
        $this->assertArrayHasKey('text', $sanitizedResult['resource']);
        $this->assertEquals($uri, $sanitizedResult['resource']['uri']);
        $this->assertEquals($mimeType, $sanitizedResult['resource']['mimeType']);
        $this->assertEquals($value, $sanitizedResult['resource']['text']);
    }

    public function test_get_sanitized_result_handles_text_plain_mime_type(): void
    {
        $uri = 'file:///path/to/file.txt';
        $mimeType = 'text/plain';
        $value = 'This is plain text content';
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('resource', $sanitizedResult['type']);
        $this->assertEquals($uri, $sanitizedResult['resource']['uri']);
        $this->assertEquals($mimeType, $sanitizedResult['resource']['mimeType']);
        $this->assertEquals($value, $sanitizedResult['resource']['text']);
    }

    public function test_get_sanitized_result_handles_xml_mime_type(): void
    {
        $uri = 'https://api.example.com/data.xml';
        $mimeType = 'application/xml';
        $value = '<?xml version="1.0"?><root><item>data</item></root>';
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('resource', $sanitizedResult['type']);
        $this->assertEquals($uri, $sanitizedResult['resource']['uri']);
        $this->assertEquals($mimeType, $sanitizedResult['resource']['mimeType']);
        $this->assertEquals($value, $sanitizedResult['resource']['text']);
    }

    public function test_get_sanitized_result_handles_empty_value(): void
    {
        $uri = 'https://example.com/empty.txt';
        $mimeType = 'text/plain';
        $value = '';
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('resource', $sanitizedResult['type']);
        $this->assertEquals($uri, $sanitizedResult['resource']['uri']);
        $this->assertEquals($mimeType, $sanitizedResult['resource']['mimeType']);
        $this->assertEquals('', $sanitizedResult['resource']['text']);
    }

    public function test_get_sanitized_result_handles_multiline_content(): void
    {
        $uri = 'file:///tmp/multiline.txt';
        $mimeType = 'text/plain';
        $value = "Line 1\nLine 2\nLine 3\n\nLine 5";
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('resource', $sanitizedResult['type']);
        $this->assertEquals($uri, $sanitizedResult['resource']['uri']);
        $this->assertEquals($mimeType, $sanitizedResult['resource']['mimeType']);
        $this->assertEquals($value, $sanitizedResult['resource']['text']);
    }

    public function test_get_sanitized_result_preserves_exact_data(): void
    {
        $uri = 'https://example.com/special-chars.json';
        $mimeType = 'application/json';
        $value = '{"special": "chars !@#$%^&*()"}';
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertSame($uri, $sanitizedResult['resource']['uri']);
        $this->assertSame($mimeType, $sanitizedResult['resource']['mimeType']);
        $this->assertSame($value, $sanitizedResult['resource']['text']);
    }

    public function test_get_sanitized_result_handles_complex_json(): void
    {
        $uri = 'https://api.example.com/complex.json';
        $mimeType = 'application/json';
        $value = '{"array":[1,2,3],"object":{"nested":"value"},"boolean":true,"null":null}';
        
        $result = new ResourceToolResult($uri, $mimeType, $value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('resource', $sanitizedResult['type']);
        $this->assertEquals($uri, $sanitizedResult['resource']['uri']);
        $this->assertEquals($mimeType, $sanitizedResult['resource']['mimeType']);
        $this->assertEquals($value, $sanitizedResult['resource']['text']);
    }
}