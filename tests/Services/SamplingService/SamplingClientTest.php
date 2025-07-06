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

    /**
     * Tests constructor with custom timeout parameter.
     */
    public function test_constructor_with_custom_timeout(): void
    {
        $customTimeout = 60;
        $samplingClient = new SamplingClient($this->transportFactory, $this->logger, $customTimeout);
        
        $this->assertTrue($samplingClient->isEnabled());
        $this->assertSame($this->logger, $samplingClient->getLogger());
    }

    /**
     * Tests debug logging when setting current client ID.
     */
    public function test_set_current_client_id_logs_debug_information(): void
    {
        $newClientId = 'new-client-123';
        $previousClientId = 'old-client-456';
        
        // Set previous client ID first
        $this->samplingClient->setCurrentClientId($previousClientId);
        
        // Test debug logging when changing client ID
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('SamplingClient::setCurrentClientId', [
                'clientId' => $newClientId,
                'previousClientId' => $previousClientId,
            ]);
        
        $this->samplingClient->setCurrentClientId($newClientId);
    }

    /**
     * Tests debug logging when setting client ID from null.
     */
    public function test_set_current_client_id_logs_debug_information_from_null(): void
    {
        $newClientId = 'first-client-789';
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('SamplingClient::setCurrentClientId', [
                'clientId' => $newClientId,
                'previousClientId' => null,
            ]);
        
        $this->samplingClient->setCurrentClientId($newClientId);
    }

    /**
     * Tests debug logging in canSample when early return occurs (disabled).
     */
    public function test_can_sample_logs_debug_when_disabled(): void
    {
        $this->samplingClient->setEnabled(false);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('SamplingClient::canSample - Early return', [
                'enabled' => false,
                'currentClientId' => null,
            ]);
        
        $this->assertFalse($this->samplingClient->canSample());
    }

    /**
     * Tests debug logging in canSample when early return occurs (no client ID).
     */
    public function test_can_sample_logs_debug_when_no_client_id(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('SamplingClient::canSample - Early return', [
                'enabled' => true,
                'currentClientId' => null,
            ]);
        
        $this->assertFalse($this->samplingClient->canSample());
    }

    /**
     * Tests debug logging in canSample when adapter is null.
     */
    public function test_can_sample_logs_debug_when_no_adapter(): void
    {
        $clientId = 'client-no-adapter';
        
        $transportWithoutAdapter = $this->createMock(AbstractTransport::class);
        $transportWithoutAdapter->method('getAdapter')->willReturn(null);
        
        $transportFactory = $this->createMock(TransportFactoryInterface::class);
        $transportFactory->method('get')->willReturn($transportWithoutAdapter);
        
        $samplingClient = new SamplingClient($transportFactory, $this->logger);
        $samplingClient->setCurrentClientId($clientId);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('SamplingClient::canSample - No adapter available');
        
        $this->assertFalse($samplingClient->canSample());
    }

    /**
     * Tests debug logging in canSample when checking capability.
     */
    public function test_can_sample_logs_debug_when_checking_capability(): void
    {
        $clientId = 'client-capability-check';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->expects($this->once())
            ->method('hasSamplingCapability')
            ->with($clientId)
            ->willReturn(true);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('SamplingClient::canSample - Checking capability', [
                'clientId' => $clientId,
                'hasSamplingCapability' => true,
            ]);
        
        $this->assertTrue($this->samplingClient->canSample());
    }

    /**
     * Tests debug logging in canSample when transport factory exception occurs.
     */
    public function test_can_sample_logs_debug_on_transport_exception(): void
    {
        $clientId = 'client-transport-exception';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $exceptionMessage = 'Transport factory not initialized';
        $transportFactory = $this->createMock(TransportFactoryInterface::class);
        $transportFactory->method('get')
            ->willThrowException(new TransportFactoryException($exceptionMessage));
        
        $samplingClient = new SamplingClient($transportFactory, $this->logger);
        $samplingClient->setCurrentClientId($clientId);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('SamplingClient::canSample - Transport exception', [
                'error' => $exceptionMessage,
            ]);
        
        $this->assertFalse($samplingClient->canSample());
    }

    /**
     * Tests handleIncomingMessage ignores messages from different clients.
     */
    public function test_handle_incoming_message_ignores_different_client(): void
    {
        $currentClientId = 'current-client';
        $differentClientId = 'different-client';
        $message = ['id' => 'msg123', 'result' => ['data' => 'test']];
        
        $this->samplingClient->setCurrentClientId($currentClientId);
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with("Current Client Id: " . $currentClientId);
        
        // Should not call getResponseWaiter since it's a different client
        $this->transportFactory->expects($this->never())
            ->method('get');
        
        $this->samplingClient->handleIncomingMessage($differentClientId, $message);
    }

    /**
     * Tests handleIncomingMessage ignores messages without ID.
     */
    public function test_handle_incoming_message_ignores_message_without_id(): void
    {
        $clientId = 'test-client';
        $message = ['result' => ['data' => 'test']]; // No 'id' field
        
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with("Current Client Id: " . $clientId);
        
        // Should not call getResponseWaiter since there's no ID
        $this->transportFactory->expects($this->never())
            ->method('get');
        
        $this->samplingClient->handleIncomingMessage($clientId, $message);
    }

    /**
     * Tests handleIncomingMessage processes valid response message.
     */
    public function test_handle_incoming_message_processes_valid_response(): void
    {
        $clientId = 'test-client';
        $message = ['id' => 'msg123', 'result' => ['data' => 'test response']];
        
        $this->samplingClient->setCurrentClientId($clientId);
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('handleResponse')
            ->with($message);
        
        // Create a partial mock to override getResponseWaiter
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with("Current Client Id: " . $clientId);
        
        $samplingClientMock->handleIncomingMessage($clientId, $message);
    }

    /**
     * Tests getLogger returns the injected logger instance.
     */
    public function test_get_logger_returns_injected_logger(): void
    {
        $logger = $this->samplingClient->getLogger();
        
        $this->assertSame($this->logger, $logger);
    }

    /**
     * Tests createSamplingResponse with null response data.
     */
    public function test_create_sampling_response_with_null_data(): void
    {
        $clientId = 'test-client-null';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->method('hasSamplingCapability')->willReturn(true);
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->willReturn(null); // Null response data
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->transport->method('onMessage');
        $this->transport->method('pushMessage');
        
        $response = $samplingClientMock->createTextRequest('Test prompt');
        
        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('Error: Invalid response format', $response->getContent()->getText());
        $this->assertEquals('error', $response->getStopReason());
    }

    /**
     * Tests createSamplingResponse with non-array response data.
     */
    public function test_create_sampling_response_with_non_array_data(): void
    {
        $clientId = 'test-client-string';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->method('hasSamplingCapability')->willReturn(true);
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->willReturn('invalid string response'); // String instead of array
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->transport->method('onMessage');
        $this->transport->method('pushMessage');
        
        $response = $samplingClientMock->createTextRequest('Test prompt');
        
        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('Error: Invalid response format', $response->getContent()->getText());
        $this->assertEquals('error', $response->getStopReason());
    }

    /**
     * Tests createSamplingResponse with invalid array data that fails parsing.
     */
    public function test_create_sampling_response_with_invalid_array_data(): void
    {
        $clientId = 'test-client-invalid';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->method('hasSamplingCapability')->willReturn(true);
        
        $invalidArrayData = ['invalid' => 'structure']; // Missing required fields
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->willReturn($invalidArrayData);
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->transport->method('onMessage');
        $this->transport->method('pushMessage');
        
        $response = $samplingClientMock->createTextRequest('Test prompt');
        
        $this->assertEquals('assistant', $response->getRole());
        $this->assertStringStartsWith('Error: Failed to parse response -', $response->getContent()->getText());
        $this->assertEquals('error', $response->getStopReason());
    }

    /**
     * Tests sendSamplingRequest when response is JsonRpcErrorException.
     */
    public function test_send_sampling_request_throws_json_rpc_error_exception(): void
    {
        $clientId = 'test-client-error';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->method('hasSamplingCapability')->willReturn(true);
        
        $errorException = new JsonRpcErrorException('Sampling failed', \KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode::INTERNAL_ERROR);
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->willReturn($errorException);
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->transport->method('onMessage');
        $this->transport->method('pushMessage');
        
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Sampling failed');
        
        $samplingClientMock->createTextRequest('Test prompt');
    }

    /**
     * Tests ensureResponseHandler debug logging.
     */
    public function test_ensure_response_handler_logs_debug(): void
    {
        $clientId = 'test-client-handler';
        
        $adapter = $this->createMock(SseAdapterInterface::class);
        $adapter->method('hasSamplingCapability')->willReturn(true);
        
        $transport = $this->createMock(AbstractTransport::class);
        $transport->method('getAdapter')->willReturn($adapter);
        
        $transportFactory = $this->createMock(TransportFactoryInterface::class);
        $transportFactory->method('get')->willReturn($transport);
        
        $logger = $this->createMock(LoggerInterface::class);
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->method('waitForResponse')
            ->willReturn([
                'role' => 'assistant',
                'content' => ['type' => 'text', 'text' => 'Response'],
                'model' => 'test',
                'stopReason' => 'endTurn'
            ]);
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$transportFactory, $logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $transport->expects($this->once())
            ->method('onMessage')
            ->with([$samplingClientMock, 'handleIncomingMessage']);
        
        $logger->expects($this->atLeastOnce())
            ->method('debug')
            ->with($this->logicalOr(
                $this->equalTo('SamplingClient::canSample - Checking capability'),
                $this->equalTo('Registered sampling response handler')
            ));
        
        $transport->method('pushMessage');
        
        $samplingClientMock->createTextRequest('Test prompt');
    }

    /**
     * Tests timeout behavior - should use 1 second in test environment.
     */
    public function test_timeout_in_test_environment(): void
    {
        $clientId = 'test-client-timeout';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->method('hasSamplingCapability')->willReturn(true);
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->expects($this->once())
            ->method('waitForResponse')
            ->with($this->anything(), 1) // Should use 1 second timeout in tests
            ->willReturn([
                'role' => 'assistant',
                'content' => ['type' => 'text', 'text' => 'Response'],
                'model' => 'test',
                'stopReason' => 'endTurn'
            ]);
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->transport->method('onMessage');
        $this->transport->method('pushMessage');
        
        $samplingClientMock->createTextRequest('Test prompt');
    }

    /**
     * Tests that getResponseWaiter creates new instance with correct parameters.
     */
    public function test_get_response_waiter_creates_new_instance(): void
    {
        $customTimeout = 45;
        $samplingClient = new SamplingClient($this->transportFactory, $this->logger, $customTimeout);
        
        $responseWaiter = $samplingClient->getResponseWaiter();
        
        $this->assertInstanceOf(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class, $responseWaiter);
    }

    /**
     * Tests createRequest with empty messages array.
     */
    public function test_create_request_with_empty_messages(): void
    {
        $clientId = 'test-client-empty';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->method('hasSamplingCapability')->willReturn(true);
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Creating sampling request', [
                'clientId' => $clientId,
                'messageCount' => 0,
            ]);
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->method('waitForResponse')
            ->willReturn([
                'role' => 'assistant',
                'content' => ['type' => 'text', 'text' => 'Empty response'],
                'model' => 'test',
                'stopReason' => 'endTurn'
            ]);
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->transport->method('onMessage');
        $this->transport->method('pushMessage');
        
        $response = $samplingClientMock->createRequest([]);
        
        $this->assertEquals('assistant', $response->getRole());
    }

    /**
     * Tests that message IDs are properly prefixed and unique.
     */
    public function test_message_id_format_and_uniqueness(): void
    {
        $clientId = 'test-unique-format';
        $this->samplingClient->setCurrentClientId($clientId);
        
        $this->adapter->method('hasSamplingCapability')->willReturn(true);
        
        $capturedIds = [];
        
        $mockResponseWaiter = $this->createMock(\KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter::class);
        $mockResponseWaiter->method('waitForResponse')
            ->willReturn([
                'role' => 'assistant',
                'content' => ['type' => 'text', 'text' => 'Response'],
                'model' => 'test',
                'stopReason' => 'endTurn'
            ]);
        
        $samplingClientMock = $this->getMockBuilder(SamplingClient::class)
            ->setConstructorArgs([$this->transportFactory, $this->logger])
            ->onlyMethods(['getResponseWaiter'])
            ->getMock();
        
        $samplingClientMock->method('getResponseWaiter')->willReturn($mockResponseWaiter);
        $samplingClientMock->setCurrentClientId($clientId);
        
        $this->transport->method('onMessage');
        $this->transport->expects($this->exactly(3))
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function ($message) use (&$capturedIds) {
                    $capturedIds[] = $message['id'];
                    return true;
                })
            );
        
        // Make multiple requests to ensure uniqueness
        $samplingClientMock->createTextRequest('Request 1');
        $samplingClientMock->createTextRequest('Request 2');
        $samplingClientMock->createTextRequest('Request 3');
        
        $this->assertCount(3, $capturedIds);
        $this->assertCount(3, array_unique($capturedIds)); // All IDs should be unique
        
        foreach ($capturedIds as $id) {
            $this->assertStringStartsWith('sampling_', $id);
            $this->assertStringContainsString('.', $id); // uniqid with more_entropy=true includes a dot
        }
    }
}
