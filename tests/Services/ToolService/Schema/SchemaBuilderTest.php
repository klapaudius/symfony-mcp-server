<?php

namespace Tests\Services\ToolService\Schema;

use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaBuilder;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use PHPUnit\Framework\TestCase;

class SchemaBuilderTest extends TestCase
{
    public function test_array_of_objects_creates_correct_schema(): void
    {
        $property = SchemaBuilder::arrayOfObjects(
            'results',
            [
                'id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
            ],
            ['id', 'title'],
            'Array of search results',
            true
        );

        $this->assertEquals('results', $property->getName());
        $this->assertEquals(PropertyType::ARRAY, $property->getType());
        $this->assertEquals('Array of search results', $property->getDescription());
        $this->assertTrue($property->isRequired());

        $items = $property->getItems();
        $this->assertNotNull($items);
        $this->assertEquals('object', $items['type']);
        $this->assertEquals(['id', 'title'], $items['required']);
        $this->assertEquals([
            'id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'url' => ['type' => 'string'],
        ], $items['properties']);
    }

    public function test_complete_schema_generates_correct_json_schema(): void
    {
        $schema = new StructuredSchema(
            SchemaBuilder::arrayOfObjects(
                'results',
                [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                ],
                ['id', 'title']
            )
        );

        $expected = [
            'type' => 'object',
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'description' => '',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'title'],
                    ],
                ],
            ],
            'required' => [],
        ];

        $this->assertEquals($expected, $schema->asArray());
    }

    public function test_array_of_primitives_creates_correct_schema(): void
    {
        $property = SchemaBuilder::arrayOfPrimitives(
            'tags',
            'string',
            'Array of tag strings'
        );

        $this->assertEquals('tags', $property->getName());
        $this->assertEquals(PropertyType::ARRAY, $property->getType());
        $this->assertEquals('Array of tag strings', $property->getDescription());
        $this->assertEquals(['type' => 'string'], $property->getItems());
    }

    public function test_nested_object_creates_correct_schema(): void
    {
        $property = SchemaBuilder::nestedObject(
            'metadata',
            [
                'author' => ['type' => 'string'],
                'timestamp' => ['type' => 'integer'],
            ],
            ['author'],
            'Metadata object'
        );

        $this->assertEquals('metadata', $property->getName());
        $this->assertEquals(PropertyType::OBJECT, $property->getType());
        $this->assertEquals('Metadata object', $property->getDescription());

        $properties = $property->getProperties();
        $this->assertNotNull($properties);
        $this->assertEquals([
            'author' => ['type' => 'string'],
            'timestamp' => ['type' => 'integer'],
        ], $properties['properties']);
        $this->assertEquals(['author'], $properties['required']);
    }
}
