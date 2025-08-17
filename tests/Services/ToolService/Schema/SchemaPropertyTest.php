<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Schema;

use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class SchemaPropertyTest extends TestCase
{
    public function test_construct_with_minimal_parameters(): void
    {
        $property = new SchemaProperty(
            name: 'testName',
            type: PropertyType::STRING
        );

        $this->assertEquals('testName', $property->getName());
        $this->assertEquals(PropertyType::STRING, $property->getType());
        $this->assertEquals('', $property->getDescription());
        $this->assertEquals([], $property->getEnum());
        $this->assertEquals('', $property->getDefault());
        $this->assertFalse($property->isRequired());
    }

    public function test_construct_with_all_parameters(): void
    {
        $property = new SchemaProperty(
            name: 'complexProperty',
            type: PropertyType::INTEGER,
            description: 'A complex property for testing',
            enum: ['option1', 'option2', 'option3'],
            default: 'option1',
            required: true
        );

        $this->assertEquals('complexProperty', $property->getName());
        $this->assertEquals(PropertyType::INTEGER, $property->getType());
        $this->assertEquals('A complex property for testing', $property->getDescription());
        $this->assertEquals(['option1', 'option2', 'option3'], $property->getEnum());
        $this->assertEquals('option1', $property->getDefault());
        $this->assertTrue($property->isRequired());
    }

    public function test_construct_with_string_type(): void
    {
        $property = new SchemaProperty(
            name: 'stringProperty',
            type: PropertyType::STRING,
            description: 'A string property',
            required: true
        );

        $this->assertEquals('stringProperty', $property->getName());
        $this->assertEquals(PropertyType::STRING, $property->getType());
        $this->assertEquals('A string property', $property->getDescription());
        $this->assertTrue($property->isRequired());
    }

    public function test_construct_with_integer_type(): void
    {
        $property = new SchemaProperty(
            name: 'integerProperty',
            type: PropertyType::INTEGER,
            description: 'An integer property',
            default: '42'
        );

        $this->assertEquals('integerProperty', $property->getName());
        $this->assertEquals(PropertyType::INTEGER, $property->getType());
        $this->assertEquals('An integer property', $property->getDescription());
        $this->assertEquals('42', $property->getDefault());
        $this->assertFalse($property->isRequired());
    }

    public function test_readonly_property_immutability(): void
    {
        $property = new SchemaProperty(
            name: 'immutableProperty',
            type: PropertyType::STRING,
            description: 'This property is immutable'
        );

        // Since the class is readonly, we can't modify properties after construction
        // This test verifies the properties remain consistent
        $this->assertEquals('immutableProperty', $property->getName());
        $this->assertEquals(PropertyType::STRING, $property->getType());
        $this->assertEquals('This property is immutable', $property->getDescription());
    }

    public function test_enum_handling(): void
    {
        $enumValues = ['red', 'green', 'blue'];
        $property = new SchemaProperty(
            name: 'colorProperty',
            type: PropertyType::STRING,
            enum: $enumValues
        );

        $this->assertEquals($enumValues, $property->getEnum());
        $this->assertIsArray($property->getEnum());
        $this->assertCount(3, $property->getEnum());
    }

    public function test_empty_enum_array(): void
    {
        $property = new SchemaProperty(
            name: 'noEnumProperty',
            type: PropertyType::STRING
        );

        $this->assertEquals([], $property->getEnum());
        $this->assertIsArray($property->getEnum());
        $this->assertEmpty($property->getEnum());
    }

    public function test_default_values_behavior(): void
    {
        $property = new SchemaProperty(
            name: 'defaultTest',
            type: PropertyType::STRING,
            default: 'defaultValue'
        );

        $this->assertEquals('defaultValue', $property->getDefault());
        $this->assertIsString($property->getDefault());
    }

    public function test_empty_default_value(): void
    {
        $property = new SchemaProperty(
            name: 'noDefaultProperty',
            type: PropertyType::STRING
        );

        $this->assertEquals('', $property->getDefault());
        $this->assertIsString($property->getDefault());
    }

    public function test_required_true(): void
    {
        $property = new SchemaProperty(
            name: 'requiredProperty',
            type: PropertyType::STRING,
            required: true
        );

        $this->assertTrue($property->isRequired());
    }

    public function test_required_false(): void
    {
        $property = new SchemaProperty(
            name: 'optionalProperty',
            type: PropertyType::STRING,
            required: false
        );

        $this->assertFalse($property->isRequired());
    }

    public function test_description_edge_cases(): void
    {
        // Test with empty description
        $property1 = new SchemaProperty(
            name: 'property1',
            type: PropertyType::STRING,
            description: ''
        );
        $this->assertEquals('', $property1->getDescription());

        // Test with multiline description
        $property2 = new SchemaProperty(
            name: 'property2',
            type: PropertyType::STRING,
            description: "Line 1\nLine 2\nLine 3"
        );
        $this->assertEquals("Line 1\nLine 2\nLine 3", $property2->getDescription());

        // Test with special characters in description
        $property3 = new SchemaProperty(
            name: 'property3',
            type: PropertyType::STRING,
            description: 'Description with special chars: !@#$%^&*()_+-={}[]|\\:";\'<>?,./'
        );
        $this->assertEquals('Description with special chars: !@#$%^&*()_+-={}[]|\\:";\'<>?,./', $property3->getDescription());
    }

    public function test_name_edge_cases(): void
    {
        // Test with underscore in name
        $property1 = new SchemaProperty(
            name: 'property_with_underscores',
            type: PropertyType::STRING
        );
        $this->assertEquals('property_with_underscores', $property1->getName());

        // Test with camelCase name
        $property2 = new SchemaProperty(
            name: 'camelCasePropertyName',
            type: PropertyType::STRING
        );
        $this->assertEquals('camelCasePropertyName', $property2->getName());
    }
}
