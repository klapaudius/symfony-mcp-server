<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaComposition;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;

class ToolParamsValidator
{
    private static ?self $instance = null;

    private static array $errors = [];

    /**
     * The constructor method is private to restrict external instantiation of the class.
     *
     * @return void
     */
    private function __construct() {}

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * The clone method is private to prevent cloning of classes.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Provides a single instance of the class. If the instance does not already exist,
     * it initializes it and then returns it. Ensures that only one instance of the
     * class is created (Singleton pattern).
     *
     * @return self The single instance of the class.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Validates the provided arguments against the tool schema.
     *
     * @param  StructuredSchema|array<string, mixed>  $toolSchema  The schema defining required arguments and their types.
     * @param  array<string, mixed>  $arguments  The arguments to be validated.
     *
     * @throws ToolParamsValidatorException if validation fails.
     *
     * @todo remove the array type hint for $toolSchema on v2.0.0.
     */
    public static function validate(StructuredSchema|array $toolSchema, array $arguments): void
    {
        self::getInstance();

        // Reset per-call state: errors must reflect only the current validation.
        self::$errors = [];

        if ($toolSchema instanceof StructuredSchema) {
            $toolSchema = $toolSchema->asArray();
        }

        $valid = true;
        $properties = $toolSchema['properties'] ?? [];

        // Convert stdClass to array for easier access
        if ($properties instanceof \stdClass) {
            $properties = (array) $properties;
        }

        foreach ($arguments as $argument => $value) {
            if (! isset($properties[$argument])) {
                self::$errors[] = "Unknown argument: $argument";
                $valid = false;

                continue;
            }

            if (! self::validateProperty($properties[$argument], $value)) {
                self::$errors[] = self::buildTypeError($argument, $properties[$argument], $value);
                $valid = false;
            }
        }
        foreach ($toolSchema['required'] ?? [] as $argument) {
            // A required argument is satisfied when the key is present with a non-null,
            // non-empty-string value. Legitimate falsy values (0, 0.0, false, "0") count
            // as provided; only absent, null, or "" are treated as missing.
            $provided = array_key_exists($argument, $arguments)
                && $arguments[$argument] !== null
                && $arguments[$argument] !== '';
            if (! $provided) {
                self::$errors[] = "Missing required argument: $argument";
                $valid = false;
            }
        }

        if (! $valid) {
            throw new ToolParamsValidatorException('Tool arguments validation failed.', self::$errors);
        }
    }

    /**
     * Validates a single argument against its property schema.
     *
     * A property is described either by a composition keyword (oneOf/anyOf/allOf) or by a
     * single `type`. Composition keywords take precedence. A property with no expressible
     * type constraint validates as true.
     *
     * @param  array<string, mixed>  $schema  The property schema definition.
     * @param  mixed  $value  The value to validate.
     */
    private static function validateProperty(array $schema, mixed $value): bool
    {
        foreach (SchemaComposition::cases() as $composition) {
            if (isset($schema[$composition->value]) && is_array($schema[$composition->value])) {
                return self::validateComposition($composition, $schema[$composition->value], $value);
            }
        }

        if (isset($schema['type']) && is_string($schema['type'])) {
            return self::validateType($schema['type'], $value);
        }

        return true;
    }

    /**
     * Validates a value against a list of sub-schemas combined by a composition keyword.
     *
     * The validator only discriminates on each branch's declared `type`; branches without a
     * `type` are treated as unconstrained (they pass and are excluded from the match counts).
     * Let `matches` be the number of typed branches whose type matches the value and `typed`
     * the number of branches declaring a type:
     * - anyOf: valid when `matches >= 1` (or there are no typed branches)
     * - oneOf: valid when `matches === 1` (or there are no typed branches)
     * - allOf: valid when `matches === typed`
     *
     * Known limitation: because the check discriminates purely on the declared `type`, `oneOf`
     * branches whose types overlap (e.g. `integer` and `number`, or `array` and `object`, or two
     * `string` branches differing only by `format`/`minLength`) can match more than one branch and
     * therefore be rejected under the strict `matches === 1` rule. Use disjoint branch types with
     * `oneOf`, or `anyOf` when overlap is intended.
     *
     * @param  array<int, mixed>  $subSchemas  The sub-schemas combined by the keyword.
     * @param  mixed  $value  The value to validate.
     */
    private static function validateComposition(SchemaComposition $composition, array $subSchemas, mixed $value): bool
    {
        $typed = 0;
        $matches = 0;

        foreach ($subSchemas as $subSchema) {
            if (! is_array($subSchema) || ! isset($subSchema['type']) || ! is_string($subSchema['type'])) {
                // Unconstrained branch: always passes, excluded from the counts.
                continue;
            }

            $typed++;
            if (self::validateType($subSchema['type'], $value)) {
                $matches++;
            }
        }

        return match ($composition) {
            SchemaComposition::ANY_OF => $typed === 0 || $matches >= 1,
            SchemaComposition::ONE_OF => $typed === 0 || $matches === 1,
            SchemaComposition::ALL_OF => $matches === $typed,
        };
    }

    /**
     * Builds a human-readable type error message for an invalid argument.
     *
     * @param  array<string, mixed>  $schema  The property schema definition.
     * @param  mixed  $value  The value that failed validation.
     */
    private static function buildTypeError(string $argument, array $schema, mixed $value): string
    {
        $got = gettype($value);

        foreach (SchemaComposition::cases() as $composition) {
            if (isset($schema[$composition->value]) && is_array($schema[$composition->value])) {
                $label = match ($composition) {
                    SchemaComposition::ANY_OF => 'any of',
                    SchemaComposition::ONE_OF => 'one of',
                    SchemaComposition::ALL_OF => 'all of',
                };
                $types = self::collectBranchTypes($schema[$composition->value]);

                return "Invalid argument type for: $argument. Expected $label: ".implode(', ', $types).", got: $got";
            }
        }

        $expected = isset($schema['type']) && is_string($schema['type']) ? $schema['type'] : 'unknown';

        return "Invalid argument type for: $argument. Expected: $expected, got: $got";
    }

    /**
     * Collects the declared `type` of each typed branch in a composition.
     *
     * @param  array<int, mixed>  $subSchemas  The sub-schemas combined by the keyword.
     * @return array<int, string> The declared types, in declaration order.
     */
    private static function collectBranchTypes(array $subSchemas): array
    {
        $types = [];
        foreach ($subSchemas as $subSchema) {
            if (is_array($subSchema) && isset($subSchema['type']) && is_string($subSchema['type'])) {
                $types[] = $subSchema['type'];
            }
        }

        return $types;
    }

    /**
     * Validates if the actual value matches the expected type.
     *
     * @param  string  $expectedType  The expected data type (e.g., 'string', 'integer', 'boolean').
     * @param  mixed  $actualValue  The value to be checked against the expected type.
     * @return bool Returns true if the actual value matches the expected type; otherwise, returns false.
     */
    private static function validateType(string $expectedType, mixed $actualValue): bool
    {
        return match ($expectedType) {
            'string' => is_string($actualValue),
            'integer' => is_int($actualValue),
            'boolean' => is_bool($actualValue),
            'array' => is_array($actualValue),
            'object' => is_object($actualValue) || is_array($actualValue), // Since MCP protocol uses JSON and most implementations use associative mode for better PHP compatibility, the validator should accept both representations of JSON objects.
            'number' => is_numeric($actualValue),
            default => false
        };
    }
}
