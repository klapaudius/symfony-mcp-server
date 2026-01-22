<?php

namespace KLP\KlpMcpServer\Tests\Data\Requests;

use KLP\KlpMcpServer\Data\Requests\RequestData;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class RequestDataTest extends TestCase
{
    /**
     * Test that fromArray correctly initializes with integer ID (backward compatibility).
     */
    public function test_from_array_with_integer_id(): void
    {
        $data = [
            'method' => 'tools/list',
            'jsonrpc' => '2.0',
            'id' => 123,
            'params' => ['foo' => 'bar'],
        ];

        $requestData = RequestData::fromArray($data);

        $this->assertEquals('tools/list', $requestData->method);
        $this->assertEquals('2.0', $requestData->jsonRpc);
        $this->assertSame(123, $requestData->id);
        $this->assertEquals(['foo' => 'bar'], $requestData->params);
    }

    /**
     * Test that constructor works with integer ID.
     */
    public function test_construct_with_integer_id(): void
    {
        $requestData = new RequestData(
            method: 'tools/call',
            jsonRpc: '2.0',
            id: 456,
            params: ['tool' => 'test']
        );

        $this->assertEquals('tools/call', $requestData->method);
        $this->assertEquals('2.0', $requestData->jsonRpc);
        $this->assertSame(456, $requestData->id);
        $this->assertEquals(['tool' => 'test'], $requestData->params);
    }

    /**
     * Test that toArray preserves integer ID type.
     */
    public function test_to_array_preserves_integer_id(): void
    {
        $requestData = new RequestData(
            method: 'resources/list',
            jsonRpc: '2.0',
            id: 789,
            params: []
        );

        $result = $requestData->toArray();

        $this->assertSame(789, $result['id']);
        $this->assertIsInt($result['id']);
    }

    /**
     * Test that fromArray correctly initializes with string ID.
     */
    public function test_from_array_with_string_id(): void
    {
        $data = [
            'method' => 'tools/list',
            'jsonrpc' => '2.0',
            'id' => 'request-abc123',
            'params' => ['foo' => 'bar'],
        ];

        $requestData = RequestData::fromArray($data);

        $this->assertEquals('tools/list', $requestData->method);
        $this->assertEquals('2.0', $requestData->jsonRpc);
        $this->assertSame('request-abc123', $requestData->id);
        $this->assertEquals(['foo' => 'bar'], $requestData->params);
    }

    /**
     * Test that constructor works with string ID.
     */
    public function test_construct_with_string_id(): void
    {
        $requestData = new RequestData(
            method: 'tools/call',
            jsonRpc: '2.0',
            id: 'custom-id-xyz',
            params: ['tool' => 'test']
        );

        $this->assertEquals('tools/call', $requestData->method);
        $this->assertEquals('2.0', $requestData->jsonRpc);
        $this->assertSame('custom-id-xyz', $requestData->id);
        $this->assertEquals(['tool' => 'test'], $requestData->params);
    }

    /**
     * Test that fromArray works with UUID string ID.
     */
    public function test_from_array_with_uuid_string_id(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = [
            'method' => 'prompts/list',
            'jsonrpc' => '2.0',
            'id' => $uuid,
            'params' => [],
        ];

        $requestData = RequestData::fromArray($data);

        $this->assertEquals('prompts/list', $requestData->method);
        $this->assertEquals('2.0', $requestData->jsonRpc);
        $this->assertSame($uuid, $requestData->id);
        $this->assertIsString($requestData->id);
    }

    /**
     * Test that toArray preserves string ID type.
     */
    public function test_to_array_preserves_string_id(): void
    {
        $requestData = new RequestData(
            method: 'resources/read',
            jsonRpc: '2.0',
            id: 'string-id-123',
            params: []
        );

        $result = $requestData->toArray();

        $this->assertSame('string-id-123', $result['id']);
        $this->assertIsString($result['id']);
    }

    /**
     * Test that numeric string IDs are preserved as strings (not converted to int).
     */
    public function test_numeric_string_id_preserved(): void
    {
        $data = [
            'method' => 'tools/list',
            'jsonrpc' => '2.0',
            'id' => '123',  // String, not int
            'params' => [],
        ];

        $requestData = RequestData::fromArray($data);

        $this->assertSame('123', $requestData->id);
        $this->assertIsString($requestData->id);
        $this->assertNotSame(123, $requestData->id);
    }

    /**
     * Test complete request flow with string ID.
     */
    public function test_complete_request_flow_with_string_id(): void
    {
        $originalData = [
            'method' => 'initialize',
            'jsonrpc' => '2.0',
            'id' => 'init-request-uuid',
            'params' => ['version' => '1.0', 'capabilities' => []],
        ];

        $requestData = RequestData::fromArray($originalData);
        $reconstructedData = $requestData->toArray();

        $this->assertEquals($originalData['method'], $reconstructedData['method']);
        $this->assertEquals($originalData['jsonrpc'], $reconstructedData['jsonrpc']);
        $this->assertEquals($originalData['id'], $reconstructedData['id']);
        $this->assertEquals($originalData['params'], $reconstructedData['params']);
        $this->assertIsString($reconstructedData['id']);
    }

    /**
     * Test complete request flow with integer ID.
     */
    public function test_complete_request_flow_with_int_id(): void
    {
        $originalData = [
            'method' => 'initialize',
            'jsonrpc' => '2.0',
            'id' => 999,
            'params' => ['version' => '1.0', 'capabilities' => []],
        ];

        $requestData = RequestData::fromArray($originalData);
        $reconstructedData = $requestData->toArray();

        $this->assertEquals($originalData['method'], $reconstructedData['method']);
        $this->assertEquals($originalData['jsonrpc'], $reconstructedData['jsonrpc']);
        $this->assertEquals($originalData['id'], $reconstructedData['id']);
        $this->assertEquals($originalData['params'], $reconstructedData['params']);
        $this->assertIsInt($reconstructedData['id']);
    }

    /**
     * Test that fromArray handles empty params correctly with string ID.
     */
    public function test_from_array_with_string_id_and_empty_params(): void
    {
        $data = [
            'method' => 'tools/list',
            'jsonrpc' => '2.0',
            'id' => 'no-params-request',
        ];

        $requestData = RequestData::fromArray($data);

        $this->assertEquals('tools/list', $requestData->method);
        $this->assertEquals('2.0', $requestData->jsonRpc);
        $this->assertSame('no-params-request', $requestData->id);
        $this->assertEquals([], $requestData->params);
    }

    /**
     * Test that fromArray handles empty params correctly with integer ID.
     */
    public function test_from_array_with_integer_id_and_empty_params(): void
    {
        $data = [
            'method' => 'tools/list',
            'jsonrpc' => '2.0',
            'id' => 42,
        ];

        $requestData = RequestData::fromArray($data);

        $this->assertEquals('tools/list', $requestData->method);
        $this->assertEquals('2.0', $requestData->jsonRpc);
        $this->assertSame(42, $requestData->id);
        $this->assertEquals([], $requestData->params);
    }
}
