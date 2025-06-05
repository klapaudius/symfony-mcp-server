<?php

namespace KLP\KlpMcpServer\Transports;

abstract class AbstractTransport implements TransportInterface
{
    /**
     * Unique identifier for the client connection, generated during initialization.
     */
    protected ?string $clientId = null;

    /**
     * Retrieves the client ID. If the client ID is not already set, generates a unique ID.
     *
     * @return string The client ID.
     */
    public function getClientId(): string
    {
        if ($this->clientId === null) {
            $this->clientId = uniqid();
        }

        return $this->clientId;
    }

}
