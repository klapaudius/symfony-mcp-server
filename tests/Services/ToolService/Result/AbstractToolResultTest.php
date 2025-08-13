<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Result;

use KLP\KlpMcpServer\Services\ToolService\Result\AbstractToolResult;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class AbstractToolResultTest extends TestCase
{
    private ConcreteToolResult $toolResult;

    protected function setUp(): void
    {
        $this->toolResult = new ConcreteToolResult;
    }

    public function test_type_setter_and_getter(): void
    {
        $this->toolResult->setTestType('custom_type');

        $this->assertEquals('custom_type', $this->toolResult->getTestType());
    }

    public function test_key_setter_and_getter(): void
    {
        $this->toolResult->setTestKey('custom_key');

        $this->assertEquals('custom_key', $this->toolResult->getTestKey());
    }

    public function test_value_setter_and_getter(): void
    {
        $this->toolResult->setTestValue('custom_value');

        $this->assertEquals('custom_value', $this->toolResult->getTestValue());
    }

    public function test_properties_can_be_changed_multiple_times(): void
    {
        $this->toolResult->setTestType('type1');
        $this->toolResult->setTestKey('key1');
        $this->toolResult->setTestValue('value1');

        $this->assertEquals('type1', $this->toolResult->getTestType());
        $this->assertEquals('key1', $this->toolResult->getTestKey());
        $this->assertEquals('value1', $this->toolResult->getTestValue());

        $this->toolResult->setTestType('type2');
        $this->toolResult->setTestKey('key2');
        $this->toolResult->setTestValue('value2');

        $this->assertEquals('type2', $this->toolResult->getTestType());
        $this->assertEquals('key2', $this->toolResult->getTestKey());
        $this->assertEquals('value2', $this->toolResult->getTestValue());
    }

    public function test_properties_handle_empty_strings(): void
    {
        $this->toolResult->setTestType('');
        $this->toolResult->setTestKey('');
        $this->toolResult->setTestValue('');

        $this->assertEquals('', $this->toolResult->getTestType());
        $this->assertEquals('', $this->toolResult->getTestKey());
        $this->assertEquals('', $this->toolResult->getTestValue());
    }

    public function test_properties_handle_special_characters(): void
    {
        $specialChars = '!@#$%^&*()_+{}|:"<>?[]\\;\',./"';

        $this->toolResult->setTestType($specialChars);
        $this->toolResult->setTestKey($specialChars);
        $this->toolResult->setTestValue($specialChars);

        $this->assertEquals($specialChars, $this->toolResult->getTestType());
        $this->assertEquals($specialChars, $this->toolResult->getTestKey());
        $this->assertEquals($specialChars, $this->toolResult->getTestValue());
    }

    public function test_concrete_implementation_get_sanitized_result(): void
    {
        $this->toolResult->setTestType('test_type');
        $this->toolResult->setTestKey('test_key');
        $this->toolResult->setTestValue('test_value');

        $result = $this->toolResult->getSanitizedResult();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('test_key', $result);
        $this->assertEquals('test_type', $result['type']);
        $this->assertEquals('test_value', $result['test_key']);
    }
}

class ConcreteToolResult extends AbstractToolResult
{
    public function setTestType(string $type): void
    {
        $this->setType($type);
    }

    public function getTestType(): string
    {
        return $this->getType();
    }

    public function setTestKey(string $key): void
    {
        $this->setKey($key);
    }

    public function getTestKey(): string
    {
        return $this->getKey();
    }

    public function setTestValue(string $value): void
    {
        $this->setValue($value);
    }

    public function getTestValue(): string
    {
        return $this->getValue();
    }

    public function getSanitizedResult(): array
    {
        return [
            'type' => $this->getType(),
            $this->getKey() => $this->getValue(),
        ];
    }
}
