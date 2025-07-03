<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Transports\AbstractTransport;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SamplingClientTest extends TestCase
{
    private SamplingClient $samplingClient;
    private AbstractTransport $transport;
    private SseAdapterInterface $adapter;
    private LoggerInterface $logger;
    private TransportFactoryInterface $transportFactory;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(AbstractTransport::class);
        $this->adapter = $this->createMock(SseAdapterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->transportFactory = $this->createMock(TransportFactoryInterface::class);

        $this->transport->method('getAdapter')->willReturn($this->adapter);
        $this->transportFactory->method('create')->willReturn($this->transport);
        $this->transportFactory->method('get')->willReturn($this->transport);

        $this->samplingClient = new SamplingClient($this->transportFactory, $this->logger);
    }

    public function testIsEnabledByDefault(): void
    {
        $this->assertTrue($this->samplingClient->isEnabled());
    }

    public function testSetEnabled(): void
    {
        $this->samplingClient->setEnabled(false);
        $this->assertFalse($this->samplingClient->isEnabled());

        $this->samplingClient->setEnabled(true);
        $this->assertTrue($this->samplingClient->isEnabled());
    }

    public function testCanSampleReturnsFalseWhenDisabled(): void
    {
        $this->samplingClient->setEnabled(false);
        $this->assertFalse($this->samplingClient->canSample());
    }

    public function testCanSampleReturnsFalseWhenNoClientId(): void
    {
        $this->assertFalse($this->samplingClient->canSample());
    }

    public function testCanSampleReturnsTrueWhenClientHasCapability(): void
    {
        $clientId = 'test-client-123';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->expects($this->once())
            ->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);

        $this->assertTrue($this->samplingClient->canSample());
    }

    public function testCanSampleReturnsFalseWhenClientLacksCapability(): void
    {
        $clientId = 'test-client-456';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->expects($this->once())
            ->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(false);

        $this->assertFalse($this->samplingClient->canSample());
    }

    public function testCreateTextRequestThrowsExceptionWhenCannotSample(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Sampling is not available for the current client');

        $this->samplingClient->createTextRequest('Test prompt');
    }

    public function testCreateTextRequestSendsProperMessage(): void
    {
        $clientId = 'test-client-789';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);

        $this->transport->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) {
                    return $message['jsonrpc'] === '2.0'
                        && $message['method'] === 'sampling/createMessage'
                        && isset($message['id'])
                        && isset($message['params']['messages'])
                        && count($message['params']['messages']) === 1
                        && $message['params']['messages'][0]['role'] === 'user'
                        && $message['params']['messages'][0]['content']['type'] === 'text'
                        && $message['params']['messages'][0]['content']['text'] === 'Test prompt';
                })
            );

        try {
            $this->samplingClient->createTextRequest('Test prompt');
        } catch (JsonRpcErrorException $e) {
            // Expected exception for unimplemented response handling
            $this->assertStringContainsString('response handling not yet implemented', $e->getMessage());
        }
    }
}
