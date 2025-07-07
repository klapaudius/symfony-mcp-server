<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Result;

use KLP\KlpMcpServer\Services\ToolService\Result\StructuredToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class StructuredToolResultTest extends TestCase
{
    public function test_construct_with_array(): void
    {
        $structuredValue = [
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com'
        ];
        
        $result = new StructuredToolResult($structuredValue);
        
        $this->assertEquals($structuredValue, $result->getStructuredValue());
        
        // Test the sanitized result format includes the JSON-encoded structured value
        $sanitized = $result->getSanitizedResult();
        $this->assertEquals('text', $sanitized['type']);
        $this->assertEquals(json_encode($structuredValue), $sanitized['text']);
    }

    public function test_construct_with_nested_array(): void
    {
        $structuredValue = [
            'user' => [
                'name' => 'Jane Smith',
                'profile' => [
                    'age' => 25,
                    'location' => 'New York'
                ]
            ],
            'metadata' => [
                'created_at' => '2023-01-01',
                'updated_at' => '2023-12-31'
            ]
        ];
        
        $result = new StructuredToolResult($structuredValue);
        
        $this->assertEquals($structuredValue, $result->getStructuredValue());
        
        // Test the sanitized result contains the JSON-encoded value
        $sanitized = $result->getSanitizedResult();
        $this->assertEquals(json_encode($structuredValue), $sanitized['text']);
    }

    public function test_construct_with_empty_array(): void
    {
        $structuredValue = [];
        
        $result = new StructuredToolResult($structuredValue);
        
        $this->assertEquals([], $result->getStructuredValue());
        
        // Test the sanitized result
        $sanitized = $result->getSanitizedResult();
        $this->assertEquals('text', $sanitized['type']);
        $this->assertEquals('[]', $sanitized['text']);
    }

    public function test_get_sanitized_result(): void
    {
        $structuredValue = [
            'status' => 'success',
            'data' => ['id' => 123, 'name' => 'Test']
        ];
        
        $result = new StructuredToolResult($structuredValue);
        $sanitized = $result->getSanitizedResult();
        
        $this->assertIsArray($sanitized);
        $this->assertArrayHasKey('type', $sanitized);
        $this->assertArrayHasKey('text', $sanitized);
        $this->assertEquals('text', $sanitized['type']);
        $this->assertEquals(json_encode($structuredValue), $sanitized['text']);
    }

    public function test_get_structured_value_immutability(): void
    {
        $originalValue = [
            'counter' => 0,
            'items' => ['a', 'b', 'c']
        ];
        
        $result = new StructuredToolResult($originalValue);
        $retrievedValue = $result->getStructuredValue();
        
        // Modify the retrieved value
        $retrievedValue['counter'] = 100;
        $retrievedValue['items'][] = 'd';
        
        // Original should remain unchanged
        $this->assertEquals($originalValue, $result->getStructuredValue());
        $this->assertNotEquals($retrievedValue, $result->getStructuredValue());
    }

    public function test_construct_with_mixed_data_types(): void
    {
        $structuredValue = [
            'string_value' => 'hello',
            'integer_value' => 42,
            'boolean_value' => true,
            'null_value' => null,
            'float_value' => 3.14,
            'array_value' => [1, 2, 3],
            'object_value' => ['key' => 'value']
        ];
        
        $result = new StructuredToolResult($structuredValue);
        
        $this->assertEquals($structuredValue, $result->getStructuredValue());
        
        // Test the sanitized result contains the JSON-encoded value
        $sanitized = $result->getSanitizedResult();
        $this->assertEquals(json_encode($structuredValue), $sanitized['text']);
    }

    public function test_json_encoding_of_complex_structure(): void
    {
        $structuredValue = [
            'timestamp' => 1640995200, // Unix timestamp
            'user_data' => [
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true,
                    'privacy' => [
                        'public_profile' => false,
                        'show_email' => false
                    ]
                ],
                'activity' => [
                    'last_login' => '2023-12-31T23:59:59Z',
                    'login_count' => 1247
                ]
            ],
            'features' => ['feature_a', 'feature_b', 'feature_c']
        ];
        
        $result = new StructuredToolResult($structuredValue);
        
        // Test that JSON encoding/decoding preserves the structure
        $sanitized = $result->getSanitizedResult();
        $jsonValue = $sanitized['text'];
        $decodedValue = json_decode($jsonValue, true);
        
        $this->assertEquals($structuredValue, $decodedValue);
        $this->assertEquals($structuredValue, $result->getStructuredValue());
    }

    public function test_inherit_from_abstract_tool_result(): void
    {
        $structuredValue = ['test' => 'data'];
        $result = new StructuredToolResult($structuredValue);
        
        // Test that it inherits methods from AbstractToolResult through the public interface
        $sanitized = $result->getSanitizedResult();
        $this->assertEquals('text', $sanitized['type']);
        $this->assertArrayHasKey('text', $sanitized);
        $this->assertIsString($sanitized['text']);
    }

    public function test_construct_with_array_containing_special_characters(): void
    {
        $structuredValue = [
            'message' => 'Hello "World" with \'quotes\'',
            'path' => '/usr/local/bin',
            'regex' => '/^[a-zA-Z0-9]+$/',
            'unicode' => '🚀 Unicode symbols 💯',
            'newlines' => "Line 1\nLine 2\nLine 3"
        ];
        
        $result = new StructuredToolResult($structuredValue);
        
        $this->assertEquals($structuredValue, $result->getStructuredValue());
        
        // Test that JSON encoding handles special characters correctly
        $sanitized = $result->getSanitizedResult();
        $jsonValue = $sanitized['text'];
        $decodedValue = json_decode($jsonValue, true);
        $this->assertEquals($structuredValue, $decodedValue);
    }

    public function test_construct_with_numeric_keys(): void
    {
        $structuredValue = [
            0 => 'first item',
            1 => 'second item',
            2 => 'third item',
            'named_key' => 'named value'
        ];
        
        $result = new StructuredToolResult($structuredValue);
        
        $this->assertEquals($structuredValue, $result->getStructuredValue());
        
        // Test the sanitized result contains the JSON-encoded value
        $sanitized = $result->getSanitizedResult();
        $this->assertEquals(json_encode($structuredValue), $sanitized['text']);
    }

    public function test_construct_with_large_array(): void
    {
        // Create a larger structured array to test performance and handling
        $structuredValue = [];
        for ($i = 0; $i < 100; $i++) {
            $structuredValue["item_{$i}"] = [
                'id' => $i,
                'name' => "Item {$i}",
                'metadata' => [
                    'created' => date('Y-m-d H:i:s'),
                    'active' => ($i % 2 === 0)
                ]
            ];
        }
        
        $result = new StructuredToolResult($structuredValue);
        
        $this->assertCount(100, $result->getStructuredValue());
        $this->assertEquals($structuredValue, $result->getStructuredValue());
        
        // Test that it can be JSON encoded without issues
        $sanitized = $result->getSanitizedResult();
        $jsonValue = $sanitized['text'];
        $this->assertIsString($jsonValue);
        
        $decodedValue = json_decode($jsonValue, true);
        $this->assertEquals($structuredValue, $decodedValue);
    }

    public function test_get_sanitized_result_format(): void
    {
        $structuredValue = ['key' => 'value'];
        $result = new StructuredToolResult($structuredValue);
        $sanitized = $result->getSanitizedResult();
        
        // Test the exact format of sanitized result
        $expected = [
            'type' => 'text',
            'text' => '{"key":"value"}'
        ];
        
        $this->assertEquals($expected, $sanitized);
    }
}