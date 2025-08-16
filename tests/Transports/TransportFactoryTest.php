<?php

namespace KLP\KlpMcpServer\Tests\Transports;

use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Transports\Factory\TransportFactory;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use KLP\KlpMcpServer\Transports\SseTransport;
use KLP\KlpMcpServer\Transports\StreamableHttpTransport;
use KLP\KlpMcpServer\Transports\TransportInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

#[Small]
class TransportFactoryTest extends TestCase
{
    private RouterInterface|MockObject $router;

    private SseAdapterInterface|MockObject $adapter;

    private LoggerInterface|MockObject $logger;

    private TransportFactory $factory;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->adapter = $this->createMock(SseAdapterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->factory = new TransportFactory($this->router, $this->adapter, $this->logger);
    }

    public function test_create_returns_sse_transport_for_sse_protocol_version(): void
    {
        // Act
        $transport = $this->factory->create(MCPProtocolInterface::PROTOCOL_FIRST_VERSION);

        // Assert
        $this->assertInstanceOf(SseTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function test_create_returns_streamable_http_transport_for_streamable_http_protocol_version(): void
    {
        // Act
        $transport = $this->factory->create(MCPProtocolInterface::PROTOCOL_THIRD_VERSION);

        // Assert
        $this->assertInstanceOf(StreamableHttpTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function test_create_throws_exception_for_unsupported_protocol_version(): void
    {
        // Arrange
        $unsupportedVersion = '2023-01-01';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported protocol version: '.$unsupportedVersion);

        // Act
        $this->factory->create($unsupportedVersion);
    }

    public function test_create_passes_config_options_to_transport(): void
    {
        // Arrange
        $factory = new TransportFactory($this->router, $this->adapter, $this->logger, true, 15);

        // Act
        $transport = $factory->create(MCPProtocolInterface::PROTOCOL_FIRST_VERSION);

        // Assert
        $this->assertInstanceOf(SseTransport::class, $transport);

        // Use reflection to check if the config was applied
        $reflection = new \ReflectionClass($transport);

        $pingEnabledProperty = $reflection->getProperty('pingEnabled');
        $this->assertTrue($pingEnabledProperty->getValue($transport));

        $pingIntervalProperty = $reflection->getProperty('pingInterval');
        $this->assertEquals(15, $pingIntervalProperty->getValue($transport));
    }

    public function test_create_uses_default_config_when_not_provided(): void
    {
        // Act
        $transport = $this->factory->create(MCPProtocolInterface::PROTOCOL_FIRST_VERSION);

        // Assert
        $this->assertInstanceOf(SseTransport::class, $transport);

        // Use reflection to check if the defaults were applied
        $reflection = new \ReflectionClass($transport);

        $pingEnabledProperty = $reflection->getProperty('pingEnabled');
        $this->assertFalse($pingEnabledProperty->getValue($transport));

        $pingIntervalProperty = $reflection->getProperty('pingInterval');
        $this->assertEquals(10, $pingIntervalProperty->getValue($transport));
    }

    public function test_get_supported_versions_returns_all_supported_versions(): void
    {
        // Act
        $versions = $this->factory->getSupportedVersions();

        // Assert
        $this->assertIsArray($versions);
        $this->assertCount(3, $versions);
        $this->assertContains(MCPProtocolInterface::PROTOCOL_FIRST_VERSION, $versions);
        $this->assertContains(MCPProtocolInterface::PROTOCOL_SECOND_VERSION, $versions);
        $this->assertContains(MCPProtocolInterface::PROTOCOL_THIRD_VERSION, $versions);
    }

    public function test_factory_passes_dependencies_to_created_transport(): void
    {
        // Act
        $transport = $this->factory->create(MCPProtocolInterface::PROTOCOL_FIRST_VERSION);

        // Assert
        $this->assertInstanceOf(SseTransport::class, $transport);

        // Use reflection to check if dependencies were passed
        $reflection = new \ReflectionClass($transport);

        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $this->assertSame($this->router, $routerProperty->getValue($transport));

        $adapterProperty = $reflection->getProperty('adapter');
        $adapterProperty->setAccessible(true);
        $this->assertSame($this->adapter, $adapterProperty->getValue($transport));

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $this->assertSame($this->logger, $loggerProperty->getValue($transport));
    }
}
