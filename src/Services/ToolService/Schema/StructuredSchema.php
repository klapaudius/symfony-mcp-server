<?php

namespace KLP\KlpMcpServer\Services\ToolService\Schema;

use stdClass;

/**
 * Represents a complete input schema for MCP tools.
 *
 * This class encapsulates the collection of properties that define the structure
 * of inputs expected by a tool, conforming to the JSON Schema format required
 * by the Model Context Protocol specification.
 *
 * The schema is used to validate tool inputs and provide type information
 * to LLMs for proper tool invocation.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/server/tools
 */
class StructuredSchema
{
    /**
     * @var SchemaProperty[] Collection of schema properties
     */
    private array $properties;

    /**
     * @param  SchemaProperty  ...$properties  Variable number of schema properties that define the tool's input structure
     */
    public function __construct(SchemaProperty ...$properties)
    {
        $this->properties = $properties;
    }

    /**
     * Gets all properties defined in this schema.
     *
     * @return SchemaProperty[] Array of schema properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Converts the schema to a JSON Schema compatible array format.
     *
     * This method transforms the internal schema representation into the standard
     * JSON Schema format expected by the MCP protocol for tool input validation.
     *
     * @return array The schema in JSON Schema format with 'type', 'properties', and 'required' fields
     */
    public function asArray(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->properties as $property) {
            $propertySchema = [
                'type' => $this->getPropertyType($property),
                'description' => $property->getDescription(),
            ];

            // Handle array items
            if ($property->getType() === PropertyType::ARRAY && $property->getItems() !== null) {
                $propertySchema['items'] = $property->getItems();
            }

            // Handle object properties
            if ($property->getType() === PropertyType::OBJECT && $property->getProperties() !== null) {
                $propertySchema['properties'] = $property->getProperties();
            }

            // Add enum if specified
            if (! empty($property->getEnum())) {
                $propertySchema['enum'] = $property->getEnum();
            }

            // Add default if specified
            if (! empty($property->getDefault())) {
                $propertySchema['default'] = $property->getDefault();
            }

            // Add any additional properties (minLength, maxLength, format, etc.)
            foreach ($property->getAdditionalProperties() as $key => $value) {
                $propertySchema[$key] = $value;
            }

            $properties[$property->getName()] = $propertySchema;

            if ($property->isRequired()) {
                $required[] = $property->getName();
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties ?: new stdClass,
            'required' => $required,
        ];
    }

    private function getPropertyType(SchemaProperty $property): string
    {
        return match ($property->getType()) {
            PropertyType::STRING => 'string',
            PropertyType::INTEGER => 'integer',
            PropertyType::ARRAY => 'array',
            PropertyType::BOOLEAN => 'boolean',
            PropertyType::OBJECT => 'object',
            PropertyType::NUMBER => 'number',
        };
    }
}
