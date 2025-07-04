<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingMessage;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Transports\AbstractTransport;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryException;
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

    public function testCreateTextRequestWithAllParameters(): void
    {
        $clientId = 'test-client-full';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);

        $modelPreferences = new ModelPreferences(
            [['name' => 'claude-3-sonnet']],
            0.5,
            0.6,
            0.7
        );

        $this->transport->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) {
                    return $message['jsonrpc'] === '2.0'
                        && $message['method'] === 'sampling/createMessage'
                        && isset($message['params']['modelPreferences'])
                        && isset($message['params']['systemPrompt'])
                        && isset($message['params']['maxTokens'])
                        && $message['params']['systemPrompt'] === 'You are a helpful assistant'
                        && $message['params']['maxTokens'] === 1000;
                })
            );

        try {
            $this->samplingClient->createTextRequest(
                'Test prompt',
                $modelPreferences,
                'You are a helpful assistant',
                1000
            );
        } catch (JsonRpcErrorException $e) {
            $this->assertStringContainsString('response handling not yet implemented', $e->getMessage());
        }
    }

    public function testCreateRequestThrowsExceptionWhenCannotSample(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Sampling is not available for the current client');

        $message = new SamplingMessage('user', new SamplingContent('text', 'Hello'));
        $this->samplingClient->createRequest([$message]);
    }

    public function testCreateRequestWithMultipleMessages(): void
    {
        $clientId = 'test-client-multi';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);

        $messages = [
            new SamplingMessage('system', new SamplingContent('text', 'System prompt')),
            new SamplingMessage('user', new SamplingContent('text', 'User message')),
            new SamplingMessage('assistant', new SamplingContent('text', 'Assistant response')),
        ];

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Creating sampling request',
                [
                    'clientId' => $clientId,
                    'messageCount' => 3,
                ]
            );

        $this->transport->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) {
                    return $message['jsonrpc'] === '2.0'
                        && $message['method'] === 'sampling/createMessage'
                        && isset($message['params']['messages'])
                        && count($message['params']['messages']) === 3
                        && $message['params']['messages'][0]['role'] === 'system'
                        && $message['params']['messages'][1]['role'] === 'user'
                        && $message['params']['messages'][2]['role'] === 'assistant';
                })
            );

        try {
            $this->samplingClient->createRequest($messages);
        } catch (JsonRpcErrorException $e) {
            $this->assertStringContainsString('response handling not yet implemented', $e->getMessage());
        }
    }

    public function testCanSampleReturnsFalseWhenAdapterIsNull(): void
    {
        $clientId = 'test-client-no-adapter';
        $this->samplingClient->setCurrentClientId($clientId);

        $transportWithoutAdapter = $this->createMock(AbstractTransport::class);
        $transportWithoutAdapter->method('getAdapter')->willReturn(null);
        
        $this->transportFactory->method('get')->willReturn($transportWithoutAdapter);
        $this->transportFactory->method('create')->willReturn($transportWithoutAdapter);

        $this->assertFalse($this->samplingClient->canSample());
    }

    public function testGetTransportCreatesNewTransportWhenFactoryThrowsException(): void
    {
        $clientId = 'test-client-factory-exception';
        $this->samplingClient->setCurrentClientId($clientId);

        $transportFactory = $this->createMock(TransportFactoryInterface::class);
        $transport = $this->createMock(AbstractTransport::class);
        $adapter = $this->createMock(SseAdapterInterface::class);
        
        $transport->method('getAdapter')->willReturn($adapter);
        $adapter->method('hasSamplingCapability')->willReturn(true);

        $transportFactory->expects($this->once())
            ->method('get')
            ->willThrowException(new TransportFactoryException('Factory not initialized'));

        $transportFactory->expects($this->once())
            ->method('create')
            ->with('2025-03-26')
            ->willReturn($transport);

        $samplingClient = new SamplingClient($transportFactory, $this->logger);
        $samplingClient->setCurrentClientId($clientId);

        $this->assertTrue($samplingClient->canSample());
    }

    public function testSetCurrentClientId(): void
    {
        $clientId = 'new-client-id';
        $this->samplingClient->setCurrentClientId($clientId);
        
        // Test that the client ID is used in canSample
        $this->adapter->expects($this->once())
            ->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);

        $this->assertTrue($this->samplingClient->canSample());
    }

    public function testMessageIdIsUnique(): void
    {
        $clientId = 'test-unique-id';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->method('hasSamplingCapability')->willReturn(true);

        $capturedIds = [];

        $this->transport->expects($this->exactly(2))
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) use (&$capturedIds) {
                    $capturedIds[] = $message['id'];
                    return true;
                })
            );

        try {
            $this->samplingClient->createTextRequest('First request');
        } catch (JsonRpcErrorException $e) {
            // Expected
        }

        try {
            $this->samplingClient->createTextRequest('Second request');
        } catch (JsonRpcErrorException $e) {
            // Expected
        }

        $this->assertCount(2, $capturedIds);
        $this->assertNotSame($capturedIds[0], $capturedIds[1]);
        $this->assertStringStartsWith('sampling_', $capturedIds[0]);
        $this->assertStringStartsWith('sampling_', $capturedIds[1]);
    }
}
