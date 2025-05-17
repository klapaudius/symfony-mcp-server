<?php

namespace KLP\KlpMcpServer\Tests\Data\Requests;

use KLP\KlpMcpServer\Data\Requests\InitializeData;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class InitializeDataTest extends TestCase
{
    /**
     * Test that fromArray correctly initializes with valid data.
     */
    public function test_from_array_with_valid_data(): void
    {
        $data = [
            'version' => '2.0',
            'capabilities' => [
                'prompts' => ['prompt1', 'prompt2'],
                'tools' => ['tool1'],
                'resources' => ['resource1', 'resource2'],
            ]
        ];

        $initializeData = InitializeData::fromArray($data);

        $this->assertEquals('2.0', $initializeData->version);
        $this->assertEquals($data['capabilities'], $initializeData->capabilities);
    }

    /**
     * Test that fromArray initializes with default values if keys are absent.
     */
    public function test_from_array_with_missing_keys(): void
    {
        $data = [];

        $initializeData = InitializeData::fromArray($data);

        $this->assertEquals('1.0', $initializeData->version);
        $this->assertEquals([
            'prompts' => [],
            'tools' => [],
            'resources' => [],
        ], $initializeData->capabilities);
    }

    /**
     * Test that fromArray handles missing version but includes capabilities.
     */
    public function test_from_array_with_missing_version(): void
    {
        $data = [
            'capabilities' => [
                'prompts' => ['prompt1'],
                'tools' => ['tool1'],
                'resources' => ['resource1'],
            ]
        ];

        $initializeData = InitializeData::fromArray($data);

        $this->assertEquals('1.0', $initializeData->version);
        $this->assertEquals($data['capabilities'], $initializeData->capabilities);
    }

    /**
     * Test that fromArray handles missing capabilities but includes version.
     */
    public function test_from_array_with_missing_capabilities(): void
    {
        $data = [
            'version' => '3.0',
        ];

        $initializeData = InitializeData::fromArray($data);

        $this->assertEquals('3.0', $initializeData->version);
        $this->assertEquals([
            'prompts' => [],
            'tools' => [],
            'resources' => [],
        ], $initializeData->capabilities);
    }

    /**
     * Test that fromArray initializes with empty array for capabilities if null.
     */
    public function test_from_array_with_null_capabilities(): void
    {
        $data = [
            'version' => '2.0',
            'capabilities' => null,
        ];

        $initializeData = InitializeData::fromArray($data);

        $this->assertEquals('2.0', $initializeData->version);
        $this->assertEquals([
            'prompts' => [],
            'tools' => [],
            'resources' => [],
        ], $initializeData->capabilities);
    }

    /**
     * Test that toArray returns the expected array when initialized with valid values.
     */
    public function test_to_array_with_valid_values(): void
    {
        $initializeData = new InitializeData('2.0', [
            'prompts' => ['prompt1', 'prompt2'],
            'tools' => ['tool1'],
            'resources' => ['resource1', 'resource2'],
        ]);

        $expected = [
            'version' => '2.0',
            'capabilities' => [
                'prompts' => ['prompt1', 'prompt2'],
                'tools' => ['tool1'],
                'resources' => ['resource1', 'resource2'],
            ],
        ];

        $this->assertEquals($expected, $initializeData->toArray());
    }

    /**
     * Test that toArray returns the default array when initialized with default values.
     */
    public function test_to_array_with_default_values(): void
    {
        $initializeData = new InitializeData('1.0', [
            'prompts' => [],
            'tools' => [],
            'resources' => [],
        ]);

        $expected = [
            'version' => '1.0',
            'capabilities' => [
                'prompts' => [],
                'tools' => [],
                'resources' => [],
            ],
        ];

        $this->assertEquals($expected, $initializeData->toArray());
    }

    /**
     * Test that toArray returns an empty capabilities array when initialized with null capabilities.
     */
    public function test_to_array_with_null_capabilities(): void
    {
        $initializeData = new InitializeData('2.0', [
            'prompts' => [],
            'tools' => [],
            'resources' => [],
        ]);

        $expected = [
            'version' => '2.0',
            'capabilities' => [
                'prompts' => [],
                'tools' => [],
                'resources' => [],
            ],
        ];

        $this->assertEquals($expected, $initializeData->toArray());
    }

    /**
     * Test that toArray returns exact data passed during initialization.
     */
    public function test_to_array_with_exact_data(): void
    {
        $capabilities = [
            'prompts' => ['prompt1', 'prompt2'],
            'tools' => ['tool1', 'tool2'],
            'resources' => ['resource1'],
        ];
        $initializeData = new InitializeData('3.0', $capabilities);

        $expected = [
            'version' => '3.0',
            'capabilities' => $capabilities,
        ];

        $this->assertEquals($expected, $initializeData->toArray());
    }
}
