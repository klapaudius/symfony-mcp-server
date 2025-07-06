<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingMessage;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Transports\AbstractTransport;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryException;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
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

    public function test_is_enabled_by_default(): void
    {
        $this->assertTrue($this->samplingClient->isEnabled());
    }

    public function test_set_enabled(): void
    {
        $this->samplingClient->setEnabled(false);
        $this->assertFalse($this->samplingClient->isEnabled());

        $this->samplingClient->setEnabled(true);
        $this->assertTrue($this->samplingClient->isEnabled());
    }

    public function test_can_sample_returns_false_when_disabled(): void
    {
        $this->samplingClient->setEnabled(false);
        $this->assertFalse($this->samplingClient->canSample());
    }

    public function test_can_sample_returns_false_when_no_client_id(): void
    {
        $this->assertFalse($this->samplingClient->canSample());
    }

    public function test_can_sample_returns_true_when_client_has_capability(): void
    {
        $clientId = 'test-client-123';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->expects($this->once())
            ->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);

        $this->assertTrue($this->samplingClient->canSample());
    }

    public function test_can_sample_returns_false_when_client_lacks_capability(): void
    {
        $clientId = 'test-client-456';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->expects($this->once())
            ->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(false);

        $this->assertFalse($this->samplingClient->canSample());
    }

    public function test_create_text_request_throws_exception_when_cannot_sample(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Sampling is not available for the current client');

        $this->samplingClient->createTextRequest('Test prompt');
    }

    public function test_create_text_request_sends_proper_message(): void
    {
        $clientId = 'test-client-789';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);

        $capturedMessageId = null;
        $messageCallback = null;

        // Capture the onMessage callback
        $this->transport->expects($this->once())
            ->method('onMessage')
            ->willReturnCallback(function ($callback) use (&$messageCallback) {
                $messageCallback = $callback;
            });

        // Create a mock response waiter
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->willReturn([
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => 'Test response'
                ],
                'model' => 'test-model',
                'stopReason' => 'endTurn'
            ]);
        
        // Create a partial mock of SamplingClient to override getResponseWaiter method
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        $samplingClientMock->setEnabled(true);

        $this->transport->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) use (&$capturedMessageId) {
                    $capturedMessageId = $message['id'] ?? null;
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

        $response = $samplingClientMock->createTextRequest('Test prompt');

        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('Test response', $response->getContent()->getText());
    }

    public function test_create_text_request_with_all_parameters(): void
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

        $capturedMessageId = null;
        $messageCallback = null;

        // Capture the onMessage callback
        $this->transport->expects($this->once())
            ->method('onMessage')
            ->willReturnCallback(function ($callback) use (&$messageCallback) {
                $messageCallback = $callback;
            });

        // Create a mock response waiter
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->willReturn([
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => 'Test response with preferences'
                ],
                'model' => 'claude-3-sonnet',
                'stopReason' => 'endTurn'
            ]);
        
        // Create a partial mock of SamplingClient to override getResponseWaiter method
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        $samplingClientMock->setEnabled(true);

        $this->transport->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) use (&$capturedMessageId) {
                    $capturedMessageId = $message['id'] ?? null;
                    return $message['jsonrpc'] === '2.0'
                        && $message['method'] === 'sampling/createMessage'
                        && isset($message['params']['modelPreferences'])
                        && isset($message['params']['systemPrompt'])
                        && isset($message['params']['maxTokens'])
                        && $message['params']['systemPrompt'] === 'You are a helpful assistant'
                        && $message['params']['maxTokens'] === 1000;
                })
            );

        $response = $samplingClientMock->createTextRequest(
            'Test prompt',
            $modelPreferences,
            'You are a helpful assistant',
            1000
        );

        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('Test response with preferences', $response->getContent()->getText());
    }

    public function test_create_request_throws_exception_when_cannot_sample(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Sampling is not available for the current client');

        $message = new SamplingMessage('user', new SamplingContent('text', 'Hello'));
        $this->samplingClient->createRequest([$message]);
    }

    public function test_create_request_with_multiple_messages(): void
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

        $capturedMessageId = null;
        $messageCallback = null;

        // Capture the onMessage callback
        $this->transport->expects($this->once())
            ->method('onMessage')
            ->willReturnCallback(function ($callback) use (&$messageCallback) {
                $messageCallback = $callback;
            });

        // Create a mock response waiter
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->willReturn([
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => 'Multi-message response'
                ],
                'model' => 'test-model',
                'stopReason' => 'endTurn'
            ]);
        
        // Create a partial mock of SamplingClient to override getResponseWaiter method
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        $samplingClientMock->setEnabled(true);

        $this->transport->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) use (&$capturedMessageId) {
                    $capturedMessageId = $message['id'] ?? null;
                    return $message['jsonrpc'] === '2.0'
                        && $message['method'] === 'sampling/createMessage'
                        && isset($message['params']['messages'])
                        && count($message['params']['messages']) === 3
                        && $message['params']['messages'][0]['role'] === 'system'
                        && $message['params']['messages'][1]['role'] === 'user'
                        && $message['params']['messages'][2]['role'] === 'assistant';
                })
            );

        $response = $samplingClientMock->createRequest($messages);

        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('Multi-message response', $response->getContent()->getText());
    }

    public function test_can_sample_returns_false_when_adapter_is_null(): void
    {
        $clientId = 'test-client-no-adapter';
        $this->samplingClient->setCurrentClientId($clientId);

        $transportWithoutAdapter = $this->createMock(AbstractTransport::class);
        $transportWithoutAdapter->method('getAdapter')->willReturn(null);

        $this->transportFactory->method('get')->willReturn($transportWithoutAdapter);
        $this->transportFactory->method('create')->willReturn($transportWithoutAdapter);

        $this->assertFalse($this->samplingClient->canSample());
    }

    public function test_can_sample_returns_false_when_transport_factory_throws_exception(): void
    {
        $clientId = 'test-client-factory-exception';
        $this->samplingClient->setCurrentClientId($clientId);

        $transportFactory = $this->createMock(TransportFactoryInterface::class);

        $transportFactory->expects($this->once())
            ->method('get')
            ->willThrowException(new TransportFactoryException('Factory not initialized'));

        // Should not call create() when canSample() catches the exception
        $transportFactory->expects($this->never())
            ->method('create');

        $samplingClient = new SamplingClient($transportFactory, $this->logger);
        $samplingClient->setCurrentClientId($clientId);

        $this->assertFalse($samplingClient->canSample());
    }

    public function test_set_current_client_id(): void
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

    public function test_message_id_is_unique(): void
    {
        $clientId = 'test-unique-id';
        $this->samplingClient->setCurrentClientId($clientId);

        $this->adapter->method('hasSamplingCapability')->willReturn(true);

        $capturedIds = [];
        $messageCallback = null;

        // Capture the onMessage callback - will be called twice for each request
        $this->transport->expects($this->exactly(2))
            ->method('onMessage')
            ->willReturnCallback(function ($callback) use (&$messageCallback) {
                $messageCallback = $callback;
            });

        // Create a mock response waiter
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->exactly(2))
            ->method('waitForResponse')
            ->willReturn([
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => 'Response'
                ],
                'model' => 'test-model',
                'stopReason' => 'endTurn'
            ]);
        
        // Create a partial mock of SamplingClient to override getResponseWaiter method
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        $samplingClientMock->setEnabled(true);

        $this->transport->expects($this->exactly(2))
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) use (&$capturedIds) {
                    $capturedIds[] = $message['id'];
                    return true;
                })
            );

        $samplingClientMock->createTextRequest('First request');
        $samplingClientMock->createTextRequest('Second request');

        $this->assertCount(2, $capturedIds);
        $this->assertNotSame($capturedIds[0], $capturedIds[1]);
        $this->assertStringStartsWith('sampling_', $capturedIds[0]);
        $this->assertStringStartsWith('sampling_', $capturedIds[1]);
    }
}
