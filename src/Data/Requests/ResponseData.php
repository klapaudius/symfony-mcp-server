<?php

namespace KLP\KlpMcpServer\Data\Requests;

/**
 * JSON-RPC Request Response Data
 * Represents the data structure for a JSON-RPC response according to the MCP specification.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/index#responses
 */
class ResponseData
{
    /**
     * The client ID of the client that sent the response.
     */
    public string $clientId;

    /**
     * The id of the request.
     */
    public string|int $id;

    /**
     * The JSON-RPC version string. MUST be "2.0".
     */
    public string $jsonRpc;

    /**
     * The result of the response.
     *
     * @var array<mixed>|null
     */
    public ?array $result = null;

    /**
     * The error object if an error occurred.
     *
     * @var array<string, mixed>|null
     */
    public ?array $error = null;

    /**
     * Constructor for NotificationData.
     *
     * @param  string|int  $id  The response id (should be the same as the id of the request).
     * @param  string  $jsonRpc  The JSON-RPC version (should be "2.0").
     * @param  array<mixed>|null  $result  The response result.
     * @param  array<string, mixed>|null  $error  The response error.
     */
    public function __construct(string|int $id, string $jsonRpc, ?array $result = null, ?array $error = null)
    {
        $this->id = $id;
        $this->jsonRpc = $jsonRpc;
        $this->result = $result;
        $this->error = $error;
    }

    /**
     * Creates a NotificationData object from an array.
     *
     * @param  array<string, mixed>  $data  The source data array, typically from a decoded JSON request.
     * @return self Returns an instance of NotificationData.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            jsonRpc: $data['jsonrpc'],
            result: $data['result'] ?? null,
            error: $data['error'] ?? null
        );
    }

    /**
     * Converts the NotificationData object back into an array format suitable for JSON encoding.
     *
     * @return array<string, mixed> Returns an array representation of the response.
     */
    public function toArray(): array
    {
        $result = [
            'jsonrpc' => $this->jsonRpc,
            'id' => $this->id,
        ];

        if ($this->result !== null) {
            $result['result'] = $this->result;
        }
        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }
}
