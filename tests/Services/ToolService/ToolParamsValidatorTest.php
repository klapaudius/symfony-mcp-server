<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService;

use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Services\ToolService\ToolParamsValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ToolParamsValidator class and its validate method.
 */
class ToolParamsValidatorTest extends TestCase
{
    public function test_validate_with_valid_arguments(): void
    {
        $toolSchema = [
            'arguments' => [
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

    public function test_validate_with_missing_required_argument(): void
    {
        $toolSchema = [
            'arguments' => [
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

    public function test_validate_with_empty_optional_argument(): void
    {
        $toolSchema = [
            'arguments' => [
                'arg1' => ['type' => 'string'],
            ],
            'required' => [],
        ];

        $arguments = ['arg1' => ''];
        $this->expectNotToPerformAssertions();

        ToolParamsValidator::validate($toolSchema, $arguments);
    }

    public function test_validate_with_invalid_argument_not_in_schema(): void
    {
        $toolSchema = [
            'arguments' => [
                'arg1' => ['type' => 'string'],
            ],
            'required' => ['arg1'],
        ];

        $arguments = [
            'arg1' => 'validString',
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

    public function test_validate_with_invalid_argument_type(): void
    {
        $toolSchema = [
            'arguments' => [
                'arg1' => ['type' => 'string'],
                'arg2' => ['type' => 'integer'],
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
            $this->assertContains('Invalid argument type for: arg2. Expected: integer, got: string', $exception->getErrors());
            throw $exception;
        }
    }

    public function test_validate_with_empty_required_argument(): void
    {
        $toolSchema = [
            'arguments' => [
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
