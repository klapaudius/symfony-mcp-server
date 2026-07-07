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
     * @param  string  $name  Property name
     * @param  array<string, mixed>  $objectProperties  Properties of objects in the array
     * @param  array<string>  $requiredProperties  Required properties in each object
     * @param  string  $description  Property description
     * @param  bool  $required  Whether the array property itself is required
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

        if (! empty($requiredProperties)) {
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
     * @param  string  $name  Property name
     * @param  string  $itemType  Type of items in the array ('string', 'integer', 'number', 'boolean')
     * @param  string  $description  Property description
     * @param  bool  $required  Whether the array property is required
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
     * Creates a property whose value must match exactly one of the given sub-schemas (JSON Schema `oneOf`).
     *
     * Branches may be given as {@see PropertyType} cases (normalized to `['type' => value]`)
     * and/or as raw sub-schema arrays (passed through unchanged), allowing richer branches
     * such as `['type' => 'string', 'minLength' => 3]`.
     *
     * Example usage:
     * ```php
     * SchemaBuilder::oneOf('id', [PropertyType::STRING, PropertyType::INTEGER], 'Record id', required: true)
     * ```
     *
     * @param  string  $name  Property name
     * @param  array<int, PropertyType|array<string, mixed>>  $branches  The candidate sub-schemas
     * @param  string  $description  Property description
     * @param  bool  $required  Whether the property is required
     */
    public static function oneOf(
        string $name,
        array $branches,
        string $description = '',
        bool $required = false
    ): SchemaProperty {
        return self::composition(SchemaComposition::ONE_OF, $name, $branches, $description, $required);
    }

    /**
     * Creates a property whose value must match at least one of the given sub-schemas (JSON Schema `anyOf`).
     *
     * @param  string  $name  Property name
     * @param  array<int, PropertyType|array<string, mixed>>  $branches  The candidate sub-schemas
     * @param  string  $description  Property description
     * @param  bool  $required  Whether the property is required
     */
    public static function anyOf(
        string $name,
        array $branches,
        string $description = '',
        bool $required = false
    ): SchemaProperty {
        return self::composition(SchemaComposition::ANY_OF, $name, $branches, $description, $required);
    }

    /**
     * Creates a property whose value must match all of the given sub-schemas (JSON Schema `allOf`).
     *
     * @param  string  $name  Property name
     * @param  array<int, PropertyType|array<string, mixed>>  $branches  The sub-schemas that must all match
     * @param  string  $description  Property description
     * @param  bool  $required  Whether the property is required
     */
    public static function allOf(
        string $name,
        array $branches,
        string $description = '',
        bool $required = false
    ): SchemaProperty {
        return self::composition(SchemaComposition::ALL_OF, $name, $branches, $description, $required);
    }

    /**
     * Builds a composition SchemaProperty from a keyword and a list of branches.
     *
     * @param  array<int, PropertyType|array<string, mixed>>  $branches  The candidate sub-schemas
     */
    private static function composition(
        SchemaComposition $composition,
        string $name,
        array $branches,
        string $description,
        bool $required
    ): SchemaProperty {
        $subSchemas = array_map(
            static fn (PropertyType|array $branch): array => $branch instanceof PropertyType
                ? ['type' => $branch->value]
                : $branch,
            array_values($branches)
        );

        return new SchemaProperty(
            name: $name,
            description: $description,
            required: $required,
            composition: $composition,
            subSchemas: $subSchemas
        );
    }

    /**
     * Creates an object property with nested properties.
     *
     * @param  string  $name  Property name
     * @param  array<string, mixed>  $properties  Nested object properties
     * @param  array<string>  $requiredProperties  Required nested properties
     * @param  string  $description  Property description
     * @param  bool  $required  Whether the object property is required
     */
    public static function nestedObject(
        string $name,
        array $properties,
        array $requiredProperties = [],
        string $description = '',
        bool $required = false
    ): SchemaProperty {
        $objectSchema = ['properties' => $properties];

        if (! empty($requiredProperties)) {
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
