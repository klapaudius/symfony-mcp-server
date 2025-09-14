<?php

namespace KLP\KlpMcpServer\Services\ToolService\Schema;

/**
 * Builder class for creating complex JSON Schema structures with StructuredSchema.
 *
 * This class provides convenient methods for creating arrays of objects,
 * nested objects, and other complex schema patterns that are common in MCP tools.
 */
class SchemaBuilder
{
    /**
     * Creates an array property that contains objects with the specified properties.
     *
     * Example usage:
     * ```php
     * SchemaBuilder::arrayOfObjects('results', [
     *     'id' => ['type' => 'string'],
     *     'title' => ['type' => 'string'],
     *     'url' => ['type' => 'string']
     * ], ['id', 'title'])
     * ```
     *
     * @param string $name Property name
     * @param array<string, mixed> $objectProperties Properties of objects in the array
     * @param array<string> $requiredProperties Required properties in each object
     * @param string $description Property description
     * @param bool $required Whether the array property itself is required
     * @return SchemaProperty
     */
    public static function arrayOfObjects(
        string $name,
        array $objectProperties,
        array $requiredProperties = [],
        string $description = '',
        bool $required = false
    ): SchemaProperty {
        $items = [
            'type' => 'object',
            'properties' => $objectProperties,
        ];

        if (!empty($requiredProperties)) {
            $items['required'] = $requiredProperties;
        }

        return new SchemaProperty(
            name: $name,
            type: PropertyType::ARRAY,
            description: $description,
            required: $required,
            items: $items
        );
    }

    /**
     * Creates an array property that contains primitive values.
     *
     * @param string $name Property name
     * @param string $itemType Type of items in the array ('string', 'integer', 'number', 'boolean')
     * @param string $description Property description
     * @param bool $required Whether the array property is required
     * @return SchemaProperty
     */
    public static function arrayOfPrimitives(
        string $name,
        string $itemType,
        string $description = '',
        bool $required = false
    ): SchemaProperty {
        return new SchemaProperty(
            name: $name,
            type: PropertyType::ARRAY,
            description: $description,
            required: $required,
            items: ['type' => $itemType]
        );
    }

    /**
     * Creates an object property with nested properties.
     *
     * @param string $name Property name
     * @param array<string, mixed> $properties Nested object properties
     * @param array<string> $requiredProperties Required nested properties
     * @param string $description Property description
     * @param bool $required Whether the object property is required
     * @return SchemaProperty
     */
    public static function nestedObject(
        string $name,
        array $properties,
        array $requiredProperties = [],
        string $description = '',
        bool $required = false
    ): SchemaProperty {
        $objectSchema = ['properties' => $properties];

        if (!empty($requiredProperties)) {
            $objectSchema['required'] = $requiredProperties;
        }

        return new SchemaProperty(
            name: $name,
            type: PropertyType::OBJECT,
            description: $description,
            required: $required,
            properties: $objectSchema
        );
    }
}
