<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Schema;

use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use stdClass;

#[Small]
class StructuredSchemaTest extends TestCase
{
    public function test_construct_with_no_properties(): void
    {
        $schema = new StructuredSchema();
        
        $this->assertIsArray($schema->getProperties());
        $this->assertEmpty($schema->getProperties());
    }

    public function test_construct_with_single_property(): void
    {
        $property = new SchemaProperty(
            name: 'testProperty',
            type: PropertyType::STRING,
            description: 'A test property'
        );
        
        $schema = new StructuredSchema($property);
        
        $this->assertIsArray($schema->getProperties());
        $this->assertCount(1, $schema->getProperties());
        $this->assertEquals($property, $schema->getProperties()[0]);
    }

    public function test_construct_with_multiple_properties(): void
    {
        $property1 = new SchemaProperty(
            name: 'stringProperty',
            type: PropertyType::STRING,
            description: 'A string property'
        );
        
        $property2 = new SchemaProperty(
            name: 'integerProperty',
            type: PropertyType::INTEGER,
            description: 'An integer property',
            required: true
        );
        
        $schema = new StructuredSchema($property1, $property2);
        
        $this->assertIsArray($schema->getProperties());
        $this->assertCount(2, $schema->getProperties());
        $this->assertEquals($property1, $schema->getProperties()[0]);
        $this->assertEquals($property2, $schema->getProperties()[1]);
    }

    public function test_as_array_with_empty_schema(): void
    {
        $schema = new StructuredSchema();
        $result = $schema->asArray();
        
        $this->assertIsArray($result);
        $this->assertEquals('object', $result['type']);
        $this->assertInstanceOf(stdClass::class, $result['properties']);
        $this->assertEquals([], $result['required']);
    }

    public function test_as_array_with_single_optional_property(): void
    {
        $property = new SchemaProperty(
            name: 'optionalProperty',
            type: PropertyType::STRING,
            description: 'An optional property',
            required: false
        );
        
        $schema = new StructuredSchema($property);
        $result = $schema->asArray();
        
        $this->assertIsArray($result);
        $this->assertEquals('object', $result['type']);
        $this->assertIsArray($result['properties']);
        $this->assertArrayHasKey('optionalProperty', $result['properties']);
        $this->assertEquals([
            'type' => 'string',
            'description' => 'An optional property'
        ], $result['properties']['optionalProperty']);
        $this->assertEquals([], $result['required']);
    }

    public function test_as_array_with_single_required_property(): void
    {
        $property = new SchemaProperty(
            name: 'requiredProperty',
            type: PropertyType::INTEGER,
            description: 'A required property',
            required: true
        );
        
        $schema = new StructuredSchema($property);
        $result = $schema->asArray();
        
        $this->assertIsArray($result);
        $this->assertEquals('object', $result['type']);
        $this->assertIsArray($result['properties']);
        $this->assertArrayHasKey('requiredProperty', $result['properties']);
        $this->assertEquals([
            'type' => 'integer',
            'description' => 'A required property'
        ], $result['properties']['requiredProperty']);
        $this->assertEquals(['requiredProperty'], $result['required']);
    }

    public function test_as_array_with_mixed_properties(): void
    {
        $property1 = new SchemaProperty(
            name: 'name',
            type: PropertyType::STRING,
            description: 'User name',
            required: true
        );
        
        $property2 = new SchemaProperty(
            name: 'age',
            type: PropertyType::INTEGER,
            description: 'User age',
            required: false
        );
        
        $property3 = new SchemaProperty(
            name: 'email',
            type: PropertyType::STRING,
            description: 'User email address',
            required: true
        );
        
        $schema = new StructuredSchema($property1, $property2, $property3);
        $result = $schema->asArray();
        
        $this->assertIsArray($result);
        $this->assertEquals('object', $result['type']);
        $this->assertIsArray($result['properties']);
        $this->assertCount(3, $result['properties']);
        
        // Check properties
        $this->assertArrayHasKey('name', $result['properties']);
        $this->assertEquals([
            'type' => 'string',
            'description' => 'User name'
        ], $result['properties']['name']);
        
        $this->assertArrayHasKey('age', $result['properties']);
        $this->assertEquals([
            'type' => 'integer',
            'description' => 'User age'
        ], $result['properties']['age']);
        
        $this->assertArrayHasKey('email', $result['properties']);
        $this->assertEquals([
            'type' => 'string',
            'description' => 'User email address'
        ], $result['properties']['email']);
        
        // Check required fields
        $this->assertIsArray($result['required']);
        $this->assertCount(2, $result['required']);
        $this->assertContains('name', $result['required']);
        $this->assertContains('email', $result['required']);
        $this->assertNotContains('age', $result['required']);
    }

    public function test_property_type_mapping_string(): void
    {
        $property = new SchemaProperty(
            name: 'stringProperty',
            type: PropertyType::STRING
        );
        
        $schema = new StructuredSchema($property);
        $result = $schema->asArray();
        
        $this->assertEquals('string', $result['properties']['stringProperty']['type']);
    }

    public function test_property_type_mapping_integer(): void
    {
        $property = new SchemaProperty(
            name: 'integerProperty',
            type: PropertyType::INTEGER
        );
        
        $schema = new StructuredSchema($property);
        $result = $schema->asArray();
        
        $this->assertEquals('integer', $result['properties']['integerProperty']['type']);
    }

    public function test_empty_description_handling(): void
    {
        $property = new SchemaProperty(
            name: 'noDescProperty',
            type: PropertyType::STRING,
            description: ''
        );
        
        $schema = new StructuredSchema($property);
        $result = $schema->asArray();
        
        $this->assertEquals('', $result['properties']['noDescProperty']['description']);
    }

    public function test_json_schema_format_compliance(): void
    {
        $property1 = new SchemaProperty(
            name: 'field1',
            type: PropertyType::STRING,
            description: 'First field',
            required: true
        );
        
        $property2 = new SchemaProperty(
            name: 'field2',
            type: PropertyType::INTEGER,
            description: 'Second field',
            required: false
        );
        
        $schema = new StructuredSchema($property1, $property2);
        $result = $schema->asArray();
        
        // Check that the result conforms to JSON Schema structure
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('properties', $result);
        $this->assertArrayHasKey('required', $result);
        
        // Check type is object
        $this->assertEquals('object', $result['type']);
        
        // Check properties structure
        foreach ($result['properties'] as $propertyName => $propertyDefinition) {
            $this->assertIsString($propertyName);
            $this->assertIsArray($propertyDefinition);
            $this->assertArrayHasKey('type', $propertyDefinition);
            $this->assertArrayHasKey('description', $propertyDefinition);
        }
        
        // Check required is array
        $this->assertIsArray($result['required']);
    }

    public function test_large_number_of_properties(): void
    {
        $properties = [];
        for ($i = 0; $i < 10; $i++) {
            $properties[] = new SchemaProperty(
                name: "property{$i}",
                type: ($i % 2 === 0) ? PropertyType::STRING : PropertyType::INTEGER,
                description: "Property number {$i}",
                required: ($i % 3 === 0)
            );
        }
        
        $schema = new StructuredSchema(...$properties);
        $result = $schema->asArray();
        
        $this->assertCount(10, $result['properties']);
        $this->assertCount(4, $result['required']); // properties 0, 3, 6, 9 are required
        
        // Verify specific properties
        $this->assertEquals('string', $result['properties']['property0']['type']);
        $this->assertEquals('integer', $result['properties']['property1']['type']);
        $this->assertContains('property0', $result['required']);
        $this->assertContains('property3', $result['required']);
        $this->assertNotContains('property1', $result['required']);
    }

    public function test_properties_order_preservation(): void
    {
        $property1 = new SchemaProperty(name: 'alpha', type: PropertyType::STRING);
        $property2 = new SchemaProperty(name: 'beta', type: PropertyType::STRING);
        $property3 = new SchemaProperty(name: 'gamma', type: PropertyType::STRING);
        
        $schema = new StructuredSchema($property1, $property2, $property3);
        $result = $schema->asArray();
        
        $propertyKeys = array_keys($result['properties']);
        $this->assertEquals(['alpha', 'beta', 'gamma'], $propertyKeys);
    }

    public function test_required_order_matches_property_definition_order(): void
    {
        $property1 = new SchemaProperty(name: 'first', type: PropertyType::STRING, required: true);
        $property2 = new SchemaProperty(name: 'second', type: PropertyType::STRING, required: false);
        $property3 = new SchemaProperty(name: 'third', type: PropertyType::STRING, required: true);
        $property4 = new SchemaProperty(name: 'fourth', type: PropertyType::STRING, required: true);
        
        $schema = new StructuredSchema($property1, $property2, $property3, $property4);
        $result = $schema->asArray();
        
        $this->assertEquals(['first', 'third', 'fourth'], $result['required']);
    }
}