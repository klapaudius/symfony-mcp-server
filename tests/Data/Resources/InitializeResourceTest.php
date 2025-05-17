<?php

namespace KLP\KlpMcpServer\Tests\Data\Resources;

use KLP\KlpMcpServer\Data\Resources\InitializeResource;
use PHPUnit\Framework\TestCase;

class InitializeResourceTest extends TestCase
{
    /**
     * Test that fromArray creates an instance with default values when the input array is empty.
     */
    public function test_from_array_with_empty_array(): void
    {
        $result = InitializeResource::fromArray([]);

        $this->assertInstanceOf(InitializeResource::class, $result);
        $this->assertSame('unknown', $result->serverInfo['name']);
        $this->assertSame('1.0', $result->serverInfo['version']);
        $this->assertSame([], $result->capabilities);
        $this->assertSame('2024-11-05', $result->protocolVersion);
    }

    /**
     * Test that fromArray correctly populates the serverInfo with the provided data.
     */
    public function test_from_array_with_valid_data(): void
    {
        $data = [
            'name' => 'TestServer',
            'version' => '2.1',
            'capabilities' => ['feature_1', 'feature_2']
        ];
        $result = InitializeResource::fromArray($data);

        $this->assertInstanceOf(InitializeResource::class, $result);
        $this->assertSame('TestServer', $result->serverInfo['name']);
        $this->assertSame('2.1', $result->serverInfo['version']);
        $this->assertSame(['feature_1', 'feature_2'], $result->capabilities);
        $this->assertSame('2024-11-05', $result->protocolVersion);
    }

    /**
     * Test that fromArray sets default for the missing 'name' key in data.
     */
    public function test_from_array_missing_name(): void
    {
        $data = [
            'version' => '2.0',
            'capabilities' => ['feature_1']
        ];
        $result = InitializeResource::fromArray($data);

        $this->assertInstanceOf(InitializeResource::class, $result);
        $this->assertSame('unknown', $result->serverInfo['name']);
        $this->assertSame('2.0', $result->serverInfo['version']);
        $this->assertSame(['feature_1'], $result->capabilities);
        $this->assertSame('2024-11-05', $result->protocolVersion);
    }

    /**
     * Test that fromArray sets default for the missing 'version' key in data.
     */
    public function test_from_array_missing_version(): void
    {
        $data = [
            'name' => 'Server A',
            'capabilities' => []
        ];
        $result = InitializeResource::fromArray($data);

        $this->assertInstanceOf(InitializeResource::class, $result);
        $this->assertSame('Server A', $result->serverInfo['name']);
        $this->assertSame('1.0', $result->serverInfo['version']);
        $this->assertSame([], $result->capabilities);
        $this->assertSame('2024-11-05', $result->protocolVersion);
    }

    /**
     * Test that fromArray sets default for the missing 'capabilities' key in data.
     */
    public function test_from_array_missing_capabilities(): void
    {
        $data = [
            'name' => 'Server B',
            'version' => '3.0',
        ];
        $result = InitializeResource::fromArray($data);

        $this->assertInstanceOf(InitializeResource::class, $result);
        $this->assertSame('Server B', $result->serverInfo['name']);
        $this->assertSame('3.0', $result->serverInfo['version']);
        $this->assertSame([], $result->capabilities);
        $this->assertSame('2024-11-05', $result->protocolVersion);
    }

    /**
     * Test that toArray returns the correct array when all properties are set.
     */
    public function test_to_array_with_all_properties(): void
    {
        $resource = new InitializeResource('Server A', '2.5', ['feature_1', 'feature_2'], '2025-01-01');
        $expected = [
            'protocolVersion' => '2025-01-01',
            'capabilities' => ['feature_1', 'feature_2'],
            'serverInfo' => [
                'name' => 'Server A',
                'version' => '2.5',
            ]
        ];

        $this->assertSame($expected, $resource->toArray());
    }

    /**
     * Test that toArray includes default values when no properties are explicitly set.
     */
    public function test_to_array_with_default_values(): void
    {
        $resource = new InitializeResource('unknown', '1.0', []);
        $expected = [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'serverInfo' => [
                'name' => 'unknown',
                'version' => '1.0',
            ]
        ];

        $this->assertSame($expected, $resource->toArray());
    }

    /**
     * Test that toArray includes mixed default and provided values.
     */
    public function test_to_array_with_partial_properties(): void
    {
        $resource = new InitializeResource('Server B', '3.0', []);
        $expected = [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'serverInfo' => [
                'name' => 'Server B',
                'version' => '3.0',
            ]
        ];

        $this->assertSame($expected, $resource->toArray());
    }
}
