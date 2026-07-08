<?php

namespace KLP\KlpMcpServer\Services\ToolService\Schema;

use InvalidArgumentException;

/**
 * Represents a single property in a tool's input schema.
 *
 * This class defines the structure of individual parameters that can be passed
 * to MCP tools, following the JSON Schema specification used by the Model Context Protocol.
 *
 * A property is described either by a single {@see PropertyType} ($type) or by a
 * composition keyword ($composition + $subSchemas), but never by both.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/server/tools
 */
readonly class SchemaProperty
{
    /**
     * @param  string  $name  The property name as it will appear in the JSON Schema
     * @param  PropertyType|null  $type  The JSON Schema type (null when the property is described by a composition keyword)
     * @param  string  $description  A human-readable description of the property
     * @param  array  $enum  An array of allowed values for this property
     * @param  string  $default  The default value for this property
     * @param  bool  $required  Whether this property is required in the input schema
     * @param  array|null  $items  For array types: defines the schema of array items
     * @param  array|null  $properties  For object types: defines the nested object properties
     * @param  array  $additionalProperties  Additional JSON Schema properties (e.g., minLength, maxLength, format)
     * @param  SchemaComposition|null  $composition  The composition keyword (oneOf/anyOf/allOf) describing this property, if any
     * @param  array<int, array<string, mixed>>  $subSchemas  The list of sub-schemas combined by $composition
     *
     * @throws InvalidArgumentException When neither a type nor a non-empty composition is provided, or when both are provided.
     */
    public function __construct(
        private string $name,
        private ?PropertyType $type = null,
        private string $description = '',
        private array $enum = [],
        private string $default = '',
        private bool $required = false,
        private ?array $items = null,
        private ?array $properties = null,
        private array $additionalProperties = [],
        private ?SchemaComposition $composition = null,
        private array $subSchemas = []
    ) {
        $hasType = $this->type !== null;
        $hasComposition = $this->composition !== null;

        if ($hasComposition && empty($this->subSchemas)) {
            throw new InvalidArgumentException(
                "SchemaProperty \"{$name}\" declares a {$this->composition->value} composition but provides no sub-schemas."
            );
        }

        if ($hasType === $hasComposition) {
            throw new InvalidArgumentException(
                "SchemaProperty \"{$name}\" must declare exactly one of a type or a composition keyword (oneOf/anyOf/allOf)."
            );
        }
    }

    /**
     * Gets the property name.
     *
     * @return string The property name as defined in the JSON Schema
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the property type.
     *
     * @return PropertyType|null The JSON Schema type for this property, or null when described by a composition keyword
     */
    public function getType(): ?PropertyType
    {
        return $this->type;
    }

    /**
     * Gets the composition keyword describing this property.
     *
     * @return SchemaComposition|null The composition keyword (oneOf/anyOf/allOf), or null when described by a single type
     */
    public function getComposition(): ?SchemaComposition
    {
        return $this->composition;
    }

    /**
     * Gets the sub-schemas combined by the composition keyword.
     *
     * @return array<int, array<string, mixed>> The list of sub-schemas, empty when no composition is set
     */
    public function getSubSchemas(): array
    {
        return $this->subSchemas;
    }

    /**
     * Checks if the property is required.
     *
     * @return bool True if the property must be present in tool inputs
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Gets the property description.
     *
     * @return string The human-readable description of this property
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    public function getEnum(): array
    {
        return $this->enum;
    }

    public function getDefault(): string
    {
        return $this->default;
    }

    /**
     * Gets the items schema for array properties.
     *
     * @return array|null The items schema definition, or null if not applicable
     */
    public function getItems(): ?array
    {
        return $this->items;
    }

    /**
     * Gets the properties schema for object properties.
     *
     * @return array|null The nested properties definition, or null if not applicable
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    /**
     * Gets additional JSON Schema properties.
     *
     * @return array Additional properties like minLength, maxLength, format, etc.
     */
    public function getAdditionalProperties(): array
    {
        return $this->additionalProperties;
    }
}
