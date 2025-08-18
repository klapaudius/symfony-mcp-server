<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
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
            $test = isset($properties[$argument])
                && self::validateType($properties[$argument]['type'], $value);
            if (! $test) {
                self::$errors[] = isset($properties[$argument])
                    ? "Invalid argument type for: $argument. Expected: {$properties[$argument]['type']}, got: ".gettype($value)
                    : "Unknown argument: $argument";
            }
            $valid &= $test;
        }
        foreach ($toolSchema['required'] ?? [] as $argument) {
            $test = ! empty($arguments[$argument]);
            if (! $test) {
                self::$errors[] = "Missing required argument: $argument";
            }
            $valid &= $test;
        }

        if (! $valid) {
            throw new ToolParamsValidatorException('Tool arguments validation failed.', self::$errors);
        }
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
            'object' => is_object($actualValue),
            'number' => is_numeric($actualValue),
            default => false
        };
    }
}
