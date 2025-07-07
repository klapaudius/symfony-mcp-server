<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService;

use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Services\ToolService\ToolParamsValidator;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;
use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ToolParamsValidatorTest extends TestCase
{
    /**
     * Tests the `validate` method of `ToolParamsValidator` with valid arguments.
     *
     * Ensures that the provided valid arguments pass the validation process defined
     * by the tool schema without throwing any exceptions or errors.
     */
    public function test_validate_with_valid_arguments(): void
    {
        $toolSchema = [
            'properties' => [
                'arg1' => ['type' => 'string'],
                'arg2' => ['type' => 'integer'],
            ],
            'required' => ['arg1'],
        ];

        $arguments = [
            'arg1' => 'validString',
            'arg2' => 123,
        ];

        $this->expectNotToPerformAssertions();

        ToolParamsValidator::validate($toolSchema, $arguments);
    }

    /**
     * Tests the `validate` method of `ToolParamsValidator` with missing required arguments.
     *
     * Verifies that the validation process throws a `ToolParamsValidatorException` when
     * the provided arguments are missing one or more keys that are marked as required
     * in the tool schema. Confirms the exception contains specific error details about
     * the missing argument(s).
     */
    public function test_validate_with_missing_required_argument(): void
    {
        $toolSchema = [
            'properties' => [
                'arg1' => ['type' => 'string'],
                'arg2' => ['type' => 'integer'],
            ],
            'required' => ['arg1', 'arg2'],
        ];

        $arguments = [
            'arg1' => 'validString',
        ];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($toolSchema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $this->assertContains('Missing required argument: arg2', $exception->getErrors());
            throw $exception;
        }
    }

    /**
     * Tests the validation process when an optional argument is provided with an empty string value.
     */
    public function test_validate_with_empty_optional_argument(): void
    {
        $toolSchema = [
            'properties' => [
                'arg1' => ['type' => 'string'],
            ],
            'required' => [],
        ];

        $arguments = ['arg1' => ''];
        $this->expectNotToPerformAssertions();

        ToolParamsValidator::validate($toolSchema, $arguments);
    }

    /**
     * Tests the validation process when an invalid argument that is not defined in the schema is provided.
     */
    public function test_validate_with_invalid_argument_not_in_schema(): void
    {
        $toolSchema = [
            'properties' => [
                'arg1' => ['type' => 'array'],
            ],
            'required' => ['arg1'],
        ];

        $arguments = [
            'arg1' => ['validArray'],
            'arg2' => 'extra',
        ];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($toolSchema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $this->assertContains('Unknown argument: arg2', $exception->getErrors());
            throw $exception;
        }
    }

    /**
     * Tests the validation process when a required argument is provided with an invalid type.
     */
    public function test_validate_with_invalid_argument_type(): void
    {
        $toolSchema = [
            'properties' => [
                'arg1' => ['type' => 'string'],
                'arg2' => ['type' => 'boolean'],
            ],
            'required' => ['arg1', 'arg2'],
        ];

        $arguments = [
            'arg1' => 'validString',
            'arg2' => 'invalidType',
        ];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($toolSchema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $this->assertContains('Invalid argument type for: arg2. Expected: boolean, got: string', $exception->getErrors());
            throw $exception;
        }
    }

    /**
     * Tests the validation process when a required argument is provided with an empty string value.
     */
    public function test_validate_with_empty_required_argument(): void
    {
        $toolSchema = [
            'properties' => [
                'arg1' => ['type' => 'string'],
            ],
            'required' => ['arg1'],
        ];

        $arguments = ['arg1' => ''];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($toolSchema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $this->assertContains('Missing required argument: arg1', $exception->getErrors());
            throw $exception;
        }
    }

    /**
     * Tests validation with StructuredSchema containing valid arguments.
     */
    public function test_validate_with_structured_schema_valid_arguments(): void
    {
        $schema = new StructuredSchema(
            new SchemaProperty(
                name: 'name',
                type: PropertyType::STRING,
                description: 'User name',
                required: true
            ),
            new SchemaProperty(
                name: 'age',
                type: PropertyType::INTEGER,
                description: 'User age',
                required: false
            )
        );

        $arguments = [
            'name' => 'John Doe',
            'age' => 30
        ];

        $this->expectNotToPerformAssertions();
        ToolParamsValidator::validate($schema, $arguments);
    }

    /**
     * Tests validation with StructuredSchema when required arguments are missing.
     */
    public function test_validate_with_structured_schema_missing_required(): void
    {
        $schema = new StructuredSchema(
            new SchemaProperty(
                name: 'name',
                type: PropertyType::STRING,
                description: 'User name',
                required: true
            ),
            new SchemaProperty(
                name: 'email',
                type: PropertyType::STRING,
                description: 'User email',
                required: true
            )
        );

        $arguments = [
            'name' => 'John Doe'
            // Missing required 'email'
        ];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($schema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $this->assertContains('Missing required argument: email', $exception->getErrors());
            throw $exception;
        }
    }

    /**
     * Tests validation with StructuredSchema when argument types are invalid.
     */
    public function test_validate_with_structured_schema_invalid_types(): void
    {
        $schema = new StructuredSchema(
            new SchemaProperty(
                name: 'name',
                type: PropertyType::STRING,
                description: 'User name',
                required: true
            ),
            new SchemaProperty(
                name: 'age',
                type: PropertyType::INTEGER,
                description: 'User age',
                required: true
            )
        );

        $arguments = [
            'name' => 123, // Should be string
            'age' => 'thirty' // Should be integer
        ];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($schema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $errors = $exception->getErrors();
            $this->assertContains('Invalid argument type for: name. Expected: string, got: integer', $errors);
            $this->assertContains('Invalid argument type for: age. Expected: integer, got: string', $errors);
            throw $exception;
        }
    }

    /**
     * Tests validation with StructuredSchema when unknown arguments are provided.
     */
    public function test_validate_with_structured_schema_unknown_arguments(): void
    {
        $schema = new StructuredSchema(
            new SchemaProperty(
                name: 'name',
                type: PropertyType::STRING,
                description: 'User name',
                required: true
            )
        );

        $arguments = [
            'name' => 'John Doe',
            'unknown_field' => 'some value'
        ];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($schema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $this->assertContains('Unknown argument: unknown_field', $exception->getErrors());
            throw $exception;
        }
    }

    /**
     * Tests validation with empty StructuredSchema.
     */
    public function test_validate_with_empty_structured_schema(): void
    {
        $schema = new StructuredSchema();
        $arguments = [];

        $this->expectNotToPerformAssertions();
        ToolParamsValidator::validate($schema, $arguments);
    }

    /**
     * Tests validation with empty StructuredSchema but arguments provided.
     */
    public function test_validate_with_empty_structured_schema_with_arguments(): void
    {
        $schema = new StructuredSchema();
        $arguments = ['unexpected' => 'value'];

        $this->expectException(ToolParamsValidatorException::class);
        $this->expectExceptionMessage('Tool arguments validation failed.');

        try {
            ToolParamsValidator::validate($schema, $arguments);
        } catch (ToolParamsValidatorException $exception) {
            $this->assertContains('Unknown argument: unexpected', $exception->getErrors());
            throw $exception;
        }
    }

    /**
     * Tests validation with StructuredSchema containing only optional arguments.
     */
    public function test_validate_with_structured_schema_only_optional(): void
    {
        $schema = new StructuredSchema(
            new SchemaProperty(
                name: 'optionalString',
                type: PropertyType::STRING,
                description: 'Optional string field',
                required: false
            ),
            new SchemaProperty(
                name: 'optionalInt',
                type: PropertyType::INTEGER,
                description: 'Optional integer field',
                required: false
            )
        );

        // Test with no arguments
        $this->expectNotToPerformAssertions();
        ToolParamsValidator::validate($schema, []);
    }

    /**
     * Tests validation with StructuredSchema containing partial optional arguments.
     */
    public function test_validate_with_structured_schema_partial_optional(): void
    {
        $schema = new StructuredSchema(
            new SchemaProperty(
                name: 'optionalString',
                type: PropertyType::STRING,
                description: 'Optional string field',
                required: false
            ),
            new SchemaProperty(
                name: 'optionalInt',
                type: PropertyType::INTEGER,
                description: 'Optional integer field',
                required: false
            )
        );

        // Test with one optional argument
        $arguments = ['optionalString' => 'test'];

        $this->expectNotToPerformAssertions();
        ToolParamsValidator::validate($schema, $arguments);
    }

    /**
     * Tests that StructuredSchema with stdClass properties/required is handled correctly.
     */
    public function test_validate_with_structured_schema_stdclass_handling(): void
    {
        // Create a schema that when converted to array will have stdClass for empty properties
        $schema = new StructuredSchema();
        $schemaArray = $schema->asArray();
        
        // Verify that properties is stdClass when empty
        $this->assertInstanceOf(\stdClass::class, $schemaArray['properties']);
        $this->assertEquals([], $schemaArray['required']);

        // Test validation with this schema
        $arguments = [];
        
        // The test verifies stdClass handling works properly
        ToolParamsValidator::validate($schema, $arguments);
        
        // If we reach here, the validation passed without throwing an exception
        $this->assertTrue(true);
    }

    /**
     * Tests the getErrors static method.
     */
    public function test_get_errors_method(): void
    {
        $schema = new StructuredSchema(
            new SchemaProperty(
                name: 'required_field',
                type: PropertyType::STRING,
                required: true
            )
        );

        $arguments = ['invalid_field' => 'value']; // Missing required, unknown field

        try {
            ToolParamsValidator::validate($schema, $arguments);
            $this->fail('Expected ToolParamsValidatorException to be thrown');
        } catch (ToolParamsValidatorException $exception) {
            $errors = ToolParamsValidator::getErrors();
            $this->assertIsArray($errors);
            $this->assertNotEmpty($errors);
            $this->assertContains('Unknown argument: invalid_field', $errors);
            $this->assertContains('Missing required argument: required_field', $errors);
        }
    }

    /**
     * Tests getInstance method (singleton pattern).
     */
    public function test_get_instance_singleton(): void
    {
        $instance1 = ToolParamsValidator::getInstance();
        $instance2 = ToolParamsValidator::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ToolParamsValidator::class, $instance1);
    }
}
