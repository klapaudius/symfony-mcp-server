<?php

namespace KLP\KlpMcpServer\Tests\Server;

use KLP\KlpMcpServer\Data\Requests\InitializeData;
use KLP\KlpMcpServer\Data\Resources\InitializeResource;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Server\MCPServer;
use KLP\KlpMcpServer\Server\Request\ResourcesListHandler;
use KLP\KlpMcpServer\Server\Request\ResourcesReadHandler;
use KLP\KlpMcpServer\Server\Request\ToolsCallHandler;
use KLP\KlpMcpServer\Server\Request\ToolsListHandler;
use KLP\KlpMcpServer\Server\ServerCapabilities;
use KLP\KlpMcpServer\Server\ServerCapabilitiesInterface;
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierRepository;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[Small]
class MCPServerTest extends TestCase
{
    /**
     * Tests that tool repository registration correctly registers the required handlers
     */
    public function test_register_tool_repository(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $mockToolRepository = $this->createMock(ToolRepository::class);

        $mockProgressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);

        $invocations = [
            new ToolsListHandler($mockToolRepository),
            new ToolsCallHandler($mockToolRepository, $mockProgressNotifierRepository),
        ];
        $mockProtocol->expects($matcher = $this->exactly(count($invocations)))
            ->method('registerRequestHandler')
            ->with($this->callback(function ($arg) use (&$invocations, $matcher) {
                $this->assertEquals($invocations[$matcher->numberOfInvocations() - 1], $arg);

                return true;
            }));

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('capabilities')->setValue($instance, new ServerCapabilities);
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);
        $server->getProperty('progressNotifierRepository')->setValue($instance, $mockProgressNotifierRepository);

        // Act
        $instance->registerToolRepository($mockToolRepository);

        // Assert: Expectations set on the mock protocol are automatically verified.
    }

    /**
     * Tests that resource repository registration correctly registers the required handlers
     */
    public function test_register_resource_repository_registers_handlers(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $mockResourceRepository = $this->createMock(ResourceRepository::class);

        $invocations = [
            new ResourcesListHandler($mockResourceRepository),
            new ResourcesReadHandler($mockResourceRepository),
        ];
        $mockProtocol->expects($matcher = $this->exactly(count($invocations)))
            ->method('registerRequestHandler')
            ->with($this->callback(function ($arg) use (&$invocations, $matcher) {
                $this->assertEquals($invocations[$matcher->numberOfInvocations() - 1], $arg);

                return true;
            }));

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('capabilities')->setValue($instance, new ServerCapabilities);
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $instance->registerResourceRepository($mockResourceRepository);

        // Assert: Expectations set on the mock protocol are automatically verified.
    }

    /**
     * Tests that registerResourceRepository method returns the server instance for method chaining
     */
    public function test_register_resource_repository_returns_instance(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $mockResourceRepository = $this->createMock(ResourceRepository::class);

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('capabilities')->setValue($instance, new ServerCapabilities);
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $result = $instance->registerResourceRepository($mockResourceRepository);

        // Assert
        $this->assertSame($instance, $result);
    }

    /**
     * Tests that registerToolRepository method returns the server instance for method chaining
     */
    public function test_register_tool_repository_returns_instance(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $mockToolRepository = $this->createMock(ToolRepository::class);
        $mockProgressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('capabilities')->setValue($instance, new ServerCapabilities);
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);
        $server->getProperty('progressNotifierRepository')->setValue($instance, $mockProgressNotifierRepository);

        // Act
        $result = $instance->registerToolRepository($mockToolRepository);

        // Assert
        $this->assertSame($instance, $result);
    }

    /**
     * Tests that request handler registration delegates to the protocol implementation
     */
    public function test_register_request_handler(): void
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

    /**
     * Tests that the create method properly instantiates a server with basic configuration
     */
    public function test_create(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $name = 'TestServer';
        $version = '1.0';

        // Act
        $mockProgressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);
        $server = MCPServer::create($mockProtocol, $mockProgressNotifierRepository, $name, $version);

        // Assert
        $this->assertInstanceOf(MCPServer::class, $server);
        $this->assertSame($mockProtocol, $this->getPrivateProperty($server, 'protocol'));
        $this->assertSame(['name' => $name, 'version' => $version], $this->getPrivateProperty($server, 'serverInfo'));
    }

    /**
     * Tests that the create method properly instantiates a server with custom capabilities
     */
    public function test_create_with_capabilities(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $name = 'TestServer';
        $version = '1.0';
        $mockCapabilities = $this->createMock(ServerCapabilitiesInterface::class);

        // Act
        $mockProgressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);
        $server = MCPServer::create($mockProtocol, $mockProgressNotifierRepository, $name, $version, $mockCapabilities);

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

        return $prop->getValue($object);
    }

    /**
     * Tests that connect method calls the protocol's connect method
     */
    public function test_connect(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);

        $mockProtocol->expects($this->once())
            ->method('connect');

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $instance->connect();

        // Assert: Expectations set on the mock objects are automatically verified.
    }

    /**
     * Tests that disconnect method calls the protocol's disconnect method
     */
    public function test_disconnect(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);

        $mockProtocol->expects($this->once())
            ->method('disconnect');

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $instance->disconnect();

        // Assert: Expectations set on the mock objects are automatically verified.
    }

    /**
     * Tests that notification handler registration delegates to the protocol implementation
     */
    public function test_register_notification_handler(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $mockHandler = $this->createMock(NotificationHandler::class);

        $mockProtocol->expects($this->once())
            ->method('registerNotificationHandler')
            ->with($mockHandler);

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $instance->registerNotificationHandler($mockHandler);

        // Assert: Expectations set on the mock objects are automatically verified.
    }

    /**
     * Tests that setProtocolVersion stores the protocol version and calls the protocol's setProtocolVersion method
     */
    public function test_set_protocol_version(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);
        $version = '2.0';

        $mockProtocol->expects($this->once())
            ->method('setProtocolVersion')
            ->with($version);

        // Act
        $instance->setProtocolVersion($version);

        // Assert
        $this->assertSame(
            $version,
            $this->getPrivateProperty($instance, 'protocolVersion'),
            "The protocolVersion property was not correctly set."
        );
    }

    /**
     * Tests that setProtocolVersion updates the protocol's version correctly
     */
    public function test_set_protocol_version_updates_protocol(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);
        $version = '3.1';

        $mockProtocol->expects($this->once())
            ->method('setProtocolVersion')
            ->with($version);

        // Act
        $instance->setProtocolVersion($version);

        // Assert: Expectations on the mock objects are verified
    }

    /**
     * Tests that getClientId calls the protocol's getClientId method and returns the expected value
     */
    public function test_get_client_id_calls_protocol(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $expectedClientId = 'test-client-id';
        $mockProtocol->expects($this->once())
            ->method('getClientId')
            ->willReturn($expectedClientId);

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $clientId = $instance->getClientId();

        // Assert
        $this->assertSame($expectedClientId, $clientId);
    }

    /**
     * Tests that getResponseResult calls the protocol's getResponseResult method
     */
    public function test_get_response_result_calls_protocol(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $mockProtocol->expects($this->once())
            ->method('getResponseResult')
            ->with('test-client-id')
            ->willReturn([]);

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $instance->getResponseResult('test-client-id');

        // Assert: Expectations set on the mock protocol are verified.
    }

    /**
     * Tests that getResponseResult returns the expected result
     */
    public function test_get_response_result_returns_expected_result(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $expectedResult = ['key' => 'value'];
        $mockProtocol->expects($this->once())
            ->method('getResponseResult')
            ->with('test-client-id')
            ->willReturn($expectedResult);

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $result = $instance->getResponseResult('test-client-id');

        // Assert
        $this->assertSame($expectedResult, $result);
    }

    /**
     * Tests that request message is properly forwarded to the protocol implementation
     */
    public function test_request_message(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $clientId = 'some-client-id';
        $message = ['method' => 'testMethod', 'params' => ['key' => 'value']];

        $mockProtocol->expects($this->once())
            ->method('requestMessage')
            ->with($clientId, $message);

        $server = new ReflectionClass(MCPServer::class);
        $instance = $server->newInstanceWithoutConstructor();
        $server->getProperty('protocol')->setValue($instance, $mockProtocol);

        // Act
        $instance->requestMessage($clientId, $message);

        // Assert: Expectations set on the mock objects are automatically verified.
    }

    /**
     * Tests that the initialize method correctly sets the client capabilities,
     * assigns the protocol version, and marks the server as initialized
     */
    public function test_initialize_sets_capabilities_and_marks_initialized(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0'];
        $mockCapabilities = $this->createMock(ServerCapabilitiesInterface::class);
        $mockCapabilities->expects($this->once())
            ->method('toInitializeMessage')
            ->willReturn(['mock-capability' => true]);

        $mockProgressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);
        $server = MCPServer::create($mockProtocol, $mockProgressNotifierRepository, $serverInfo['name'], $serverInfo['version'], $mockCapabilities);
        $initializeData = new InitializeData('2.0', ['mock-capability' => true]);

        // Act
        $initializeResource = $server->initialize($initializeData);

        // Assert
        $this->assertTrue($this->getPrivateProperty($server, 'initialized'));
        $this->assertEquals(MCPProtocolInterface::PROTOCOL_VERSION_SSE, $initializeResource->protocolVersion);
        $this->assertSame($serverInfo, $initializeResource->serverInfo);
    }

    /**
     * Tests that the initialize method throws an exception if the server
     * has already been initialized
     */
    public function test_initialize_throws_when_already_initialized(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0'];
        $mockCapabilities = $this->createMock(ServerCapabilitiesInterface::class);

        $mockProgressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);
        $server = MCPServer::create($mockProtocol, $mockProgressNotifierRepository, $serverInfo['name'], $serverInfo['version'], $mockCapabilities);
        $initializeData = new InitializeData('2.0', ['mock-capability' => true]);

        $server->initialize($initializeData);

        // Expect

        // Act
        try {
            $server->initialize($initializeData);
            $this->fail('Expected exception not thrown');
        } catch (JsonRpcErrorException $e) {
            $this->assertEquals(-32600, $e->getJsonRpcErrorCode());
            $this->assertEquals('Server already initialized', $e->getMessage());
            $this->assertEquals($initializeData, $e->getErrorData());
        }
    }

    /**
     * Tests that the initialize method returns a correctly constructed
     * InitializeResource object with expected values
     */
    public function test_initialize_returns_correct_resource(): void
    {
        // Arrange
        $mockProtocol = $this->createMock(MCPProtocolInterface::class);
        $serverInfo = ['name' => 'TestServer', 'version' => '1.0'];
        $mockCapabilities = $this->createMock(ServerCapabilitiesInterface::class);
        $mockCapabilities->expects($this->once())
            ->method('toInitializeMessage')
            ->willReturn(['mock-capability' => true]);

        $mockProgressNotifierRepository = $this->createMock(ProgressNotifierRepository::class);
        $server = MCPServer::create($mockProtocol, $mockProgressNotifierRepository, $serverInfo['name'], $serverInfo['version'], $mockCapabilities);
        $initializeData = new InitializeData('2024-11-05', ['mock-capability' => true]);

        // Act
        $initializeResource = $server->initialize($initializeData);

        // Assert
        $this->assertInstanceOf(InitializeResource::class, $initializeResource);
        $this->assertEquals('2024-11-05', $initializeResource->protocolVersion);
        $this->assertSame(['mock-capability' => true], $initializeResource->capabilities);
        $this->assertEquals($serverInfo, $initializeResource->serverInfo);
    }
}
