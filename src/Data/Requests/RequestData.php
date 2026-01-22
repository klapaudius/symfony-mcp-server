<?php

namespace KLP\KlpMcpServer\Data\Requests;

/**
 * Represents a JSON-RPC Request object according to the MCP specification.
 * This class encapsulates the data structure for incoming requests.
 *
 * @link https://modelcontextprotocol.io/specification/2024-11-05/basic/index#requests
 *
 * @property string $method The name of the method to be invoked.
 * @property string $jsonRpc The JSON-RPC version string (e.g., "2.0").
 * @property string|int $id An identifier established by the Client (string, number, or null per JSON-RPC 2.0).
 * @property array<string, mixed> $params The parameters to be used during the invocation of the method.
 */
class RequestData
{
    /** @var string The name of the method to be invoked. */
    public string $method;

    /** @var string The JSON-RPC version string. */
    public string $jsonRpc;

    /** @var string|int An identifier established by the Client. */
    public string|int $id;

    /** @var array<string, mixed> The parameters for the method invocation. */
    public array $params;

    /**
     * Constructor for RequestData.
     *
     * @param  string  $method  The method name.
     * @param  string  $jsonRpc  The JSON-RPC version.
     * @param  string|int  $id  The request identifier.
     * @param  array<string, mixed>  $params  The request parameters.
     */
    public function __construct(string $method, string $jsonRpc, string|int $id, array $params)
    {
        $this->method = $method;
        $this->jsonRpc = $jsonRpc;
        $this->id = $id;
        $this->params = $params;
    }

    /**
     * Creates a RequestData instance from an array.
     *
     * @param  array<string, mixed>  $data  The data array, typically from a decoded JSON request.
     * @return self A new instance of RequestData.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'],
            jsonRpc: $data['jsonrpc'],
            id: $data['id'],
            params: $data['params'] ?? []
        );
    }

    /**
     * Converts the RequestData instance to an array.
     *
     * @return array<string, mixed> The array representation of the request data.
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => $this->jsonRpc,
            'id' => $this->id,
            'method' => $this->method,
            'params' => $this->params,
        ];
    }
}
