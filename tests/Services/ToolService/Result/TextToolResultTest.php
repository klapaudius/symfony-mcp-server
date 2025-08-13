<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Result;

use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class TextToolResultTest extends TestCase
{
    public function test_constructor_sets_correct_properties(): void
    {
        $value = 'This is a test text';
        $result = new TextToolResult($value);

        $this->assertInstanceOf(TextToolResult::class, $result);
    }

    public function test_get_sanitized_result_returns_correct_format(): void
    {
        $value = 'This is a test text';
        $result = new TextToolResult($value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertArrayHasKey('type', $sanitizedResult);
        $this->assertArrayHasKey('text', $sanitizedResult);
        $this->assertEquals('text', $sanitizedResult['type']);
        $this->assertEquals($value, $sanitizedResult['text']);
    }

    public function test_get_sanitized_result_handles_empty_string(): void
    {
        $value = '';
        $result = new TextToolResult($value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('text', $sanitizedResult['type']);
        $this->assertEquals('', $sanitizedResult['text']);
    }

    public function test_get_sanitized_result_handles_multiline_text(): void
    {
        $value = "Line 1\nLine 2\nLine 3";
        $result = new TextToolResult($value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('text', $sanitizedResult['type']);
        $this->assertEquals($value, $sanitizedResult['text']);
    }

    public function test_get_sanitized_result_handles_special_characters(): void
    {
        $value = 'Special chars: !@#$%^&*()_+{}|:"<>?[]\\;\',./"';
        $result = new TextToolResult($value);

        $sanitizedResult = $result->getSanitizedResult();

        $this->assertIsArray($sanitizedResult);
        $this->assertEquals('text', $sanitizedResult['type']);
        $this->assertEquals($value, $sanitizedResult['text']);
    }
}
