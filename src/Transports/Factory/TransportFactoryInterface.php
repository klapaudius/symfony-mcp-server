<?php

namespace KLP\KlpMcpServer\Transports\Factory;


use KLP\KlpMcpServer\Transports\TransportInterface;

/**
 * Factory for creating transport instances based on the protocol version.
 *
 * @since 1.2.0
 */
interface TransportFactoryInterface
{
    /**
     * Creates a transport instance based on the specified protocol version.
     *
     * @param string $protocolVersion The MCP protocol version to use.
     *
     * @return TransportInterface The created transport instance.
     *
     * @throws \InvalidArgumentException If the protocol version is not supported.
     */
    public function create(string $protocolVersion): TransportInterface;

    /**
     * Returns the list of supported protocol versions.
     *
     * @return array<string> The supported protocol versions.
     */
    public function getSupportedVersions(): array;
}
