<?php

namespace KLP\KlpMcpServer\Data\Requests;

use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;

/**
 * Initial connection request data.
 */
class InitializeData
{
    public string $version;

    public array $capabilities;

    public string $protocolVersion;

    public function __construct(string $version, array $capabilities, ?string $protocolVersion = null)
    {
        $this->version = $version;
        $this->capabilities = $capabilities;
        $this->protocolVersion = $protocolVersion ?? MCPProtocolInterface::PROTOCOL_FIRST_VERSION;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['version'] ?? '1.0',
            $data['capabilities'] ?? [
                'prompts' => [],
                'tools' => [],
                'resources' => [],
            ],
            $data['protocolVersion'] ?? MCPProtocolInterface::PROTOCOL_FIRST_VERSION,
        );
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'capabilities' => $this->capabilities,
        ];
    }
}
