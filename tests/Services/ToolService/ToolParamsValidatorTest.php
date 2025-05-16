<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService;

use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Services\ToolService\ToolParamsValidator;
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
}
