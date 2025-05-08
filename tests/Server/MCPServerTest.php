<?php

namespace KLP\KlpMcpServer\Tests\Server;

use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Server\MCPServer;
use KLP\KlpMcpServer\Server\ServerCapabilities;
use KLP\KlpMcpServer\Server\ServerCapabilitiesInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MCPServerTest extends TestCase
{
    public function testRegisterRequestHandler(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $mockHandler = $this->createMock(RequestHandler::class);

        $mockProtocol->expects($this->once())
            ->method('registerRequestHandler')
            ->with($mockHandler);
        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $instance->registerRequestHandler($mockHandler);

        // Assert: Expectations set on the mock objects are automatically verified
    }

    public function testCreate(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $name = 'TestServer';
        $version = '1.0';

        // Act
        $server = MCPServer::create($mockProtocol, $name, $version);

        // Assert
        $this->assertInstanceOf(MCPServer::class, $server);
        $this->assertSame($mockProtocol, $this->getPrivateProperty($server, 'protocol'));
        $this->assertSame(['name' => $name, 'version' => $version], $this->getPrivateProperty($server, 'serverInfo'));
    }

    public function testCreateWithCapabilities(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $name = 'TestServer';
        $version = '1.0';
        $mockCapabilities = $this->createMock(ServerCapabilitiesInterface::class);

        // Act
        $server = MCPServer::create($mockProtocol, $name, $version, $mockCapabilities);

        // Assert
        $this->assertInstanceOf(MCPServer::class, $server);
        $this->assertSame($mockProtocol, $this->getPrivateProperty($server, 'protocol'));
        $this->assertSame(['name' => $name, 'version' => $version], $this->getPrivateProperty($server, 'serverInfo'));
        $this->assertSame($mockCapabilities, $this->getPrivateProperty($server, 'capabilities'));
    }

    private function getPrivateProperty(object $object, string $property)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}
