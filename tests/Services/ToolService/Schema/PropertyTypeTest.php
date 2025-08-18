<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Schema;

use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class PropertyTypeTest extends TestCase
{
    public function test_enum_cases_exist(): void
    {
        $this->assertIsArray(PropertyType::cases());
        $this->assertCount(6, PropertyType::cases());
    }

    public function test_string_case_exists(): void
    {
        $this->assertEquals(PropertyType::STRING, PropertyType::STRING);
        $this->assertEquals('STRING', PropertyType::STRING->name);
    }

    public function test_integer_case_exists(): void
    {
        $this->assertEquals(PropertyType::INTEGER, PropertyType::INTEGER);
        $this->assertEquals('INTEGER', PropertyType::INTEGER->name);
    }

    public function test_all_cases_have_correct_names(): void
    {
        $expectedCases = ['STRING', 'INTEGER', 'NUMBER', 'OBJECT', 'ARRAY', 'BOOLEAN'];
        $actualCases = array_map(fn ($case) => $case->name, PropertyType::cases());

        $this->assertEquals($expectedCases, $actualCases);
    }

    public function test_enum_values_are_unique(): void
    {
        $cases = PropertyType::cases();
        $this->assertCount(6, $cases);
        $this->assertNotEquals($cases[0], $cases[1]);
    }

    public function test_string_case_properties(): void
    {
        $stringCase = PropertyType::STRING;
        $this->assertInstanceOf(PropertyType::class, $stringCase);
        $this->assertEquals('STRING', $stringCase->name);
    }

    public function test_integer_case_properties(): void
    {
        $integerCase = PropertyType::INTEGER;
        $this->assertInstanceOf(PropertyType::class, $integerCase);
        $this->assertEquals('INTEGER', $integerCase->name);
    }
}
