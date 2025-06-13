<?php

namespace KLP\KlpMcpServer\Tests\Services\ProgressService;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifier;
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierRepository;
use KLP\KlpMcpServer\Services\ProgressService\ProgressTokenException;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryException;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\TransportInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Small]
class ProgressNotifierRepositoryTest extends TestCase
{
    private TransportFactoryInterface|MockObject $transportFactory;
    private TransportInterface|MockObject $transport;
    private ProgressNotifierRepository $repository;

    protected function setUp(): void
    {
        $this->transportFactory = $this->createMock(TransportFactoryInterface::class);
        $this->transport = $this->createMock(TransportInterface::class);
        $this->repository = new ProgressNotifierRepository($this->transportFactory);
    }

    public function test_constructor_creates_repository(): void
    {
        $this->assertInstanceOf(ProgressNotifierRepository::class, $this->repository);
    }

    public function test_register_token_creates_progress_notifier(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $progressToken = 'test-token-123';
        $clientId = 'client-456';

        $notifier = $this->repository->registerToken($progressToken, $clientId);

        $this->assertInstanceOf(ProgressNotifier::class, $notifier);
        $this->assertTrue($this->repository->isTokenActive($progressToken));
    }

    public function test_register_token_with_integer_token(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $progressToken = 12345;
        $clientId = 'client-789';

        $notifier = $this->repository->registerToken($progressToken, $clientId);

        $this->assertInstanceOf(ProgressNotifier::class, $notifier);
        $this->assertTrue($this->repository->isTokenActive($progressToken));
    }

    public function test_register_same_token_twice_returns_null_second_time(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $progressToken = 'test-token-duplicate';
        $clientId = 'client-123';

        $notifier1 = $this->repository->registerToken($progressToken, $clientId);
        $notifier2 = $this->repository->registerToken($progressToken, $clientId);

        $this->assertInstanceOf(ProgressNotifier::class, $notifier1);
        $this->assertNull($notifier2);
        $this->assertTrue($this->repository->isTokenActive($progressToken));
    }

    public function test_register_token_throws_exception_when_transport_factory_fails(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new TransportFactoryException('Transport factory error'));

        $this->expectException(TransportFactoryException::class);
        $this->expectExceptionMessage('Transport factory error');

        $this->repository->registerToken('test-token', 'client-id');
    }

    public function test_unregister_token_removes_active_token(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $progressToken = 'test-token-unregister';
        $clientId = 'client-unregister';

        $this->repository->registerToken($progressToken, $clientId);
        $this->assertTrue($this->repository->isTokenActive($progressToken));

        $this->repository->unregisterToken($progressToken);
        $this->assertFalse($this->repository->isTokenActive($progressToken));
    }

    public function test_unregister_token_with_null_does_nothing(): void
    {
        $this->repository->unregisterToken(null);
        
        $this->assertCount(0, $this->repository->getActiveTokens());
    }

    public function test_unregister_non_existent_token_does_nothing(): void
    {
        $this->repository->unregisterToken('non-existent-token');
        
        $this->assertCount(0, $this->repository->getActiveTokens());
    }

    public function test_is_token_active_returns_false_for_non_existent_token(): void
    {
        $this->assertFalse($this->repository->isTokenActive('non-existent'));
    }

    public function test_get_active_tokens_returns_empty_array_initially(): void
    {
        $activeTokens = $this->repository->getActiveTokens();
        
        $this->assertIsArray($activeTokens);
        $this->assertCount(0, $activeTokens);
    }

    public function test_get_active_tokens_returns_registered_tokens(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $token1 = 'token-1';
        $token2 = 'token-2';
        $token3 = 12345;

        $this->repository->registerToken($token1, 'client-1');
        $this->repository->registerToken($token2, 'client-2');
        $this->repository->registerToken($token3, 'client-3');

        $activeTokens = $this->repository->getActiveTokens();
        
        $this->assertCount(3, $activeTokens);
        $this->assertContains($token1, $activeTokens);
        $this->assertContains($token2, $activeTokens);
        $this->assertContains($token3, $activeTokens);
    }

    public function test_handle_message_pushes_message_to_transport(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $progressToken = 'test-handle-message';
        $clientId = 'client-handle';
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => [
                'progressToken' => $progressToken,
                'progress' => 50,
                'total' => 100,
                'message' => 'Test message'
            ]
        ];

        $this->transport
            ->expects($this->once())
            ->method('pushMessage')
            ->with($clientId, $message);

        $this->repository->registerToken($progressToken, $clientId);
        $this->repository->handleMessage($message);
    }

    public function test_handle_message_throws_exception_for_inactive_token(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => [
                'progressToken' => 'inactive-token',
                'progress' => 50
            ]
        ];

        $this->expectException(ProgressTokenException::class);
        $this->expectExceptionMessage('Invalid progress token: inactive-token is not active');

        $this->repository->handleMessage($message);
    }

    public function test_multiple_tokens_with_different_clients(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $token1 = 'token-client-1';
        $token2 = 'token-client-2';
        $clientId1 = 'client-1';
        $clientId2 = 'client-2';

        $this->repository->registerToken($token1, $clientId1);
        $this->repository->registerToken($token2, $clientId2);

        $this->assertTrue($this->repository->isTokenActive($token1));
        $this->assertTrue($this->repository->isTokenActive($token2));

        $message1 = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => ['progressToken' => $token1, 'progress' => 25]
        ];

        $message2 = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => ['progressToken' => $token2, 'progress' => 75]
        ];

        $this->transport
            ->expects($this->exactly(2))
            ->method('pushMessage')
            ->willReturnCallback(function ($clientId, $message) use ($clientId1, $clientId2, $message1, $message2) {
                $this->assertTrue(
                    ($clientId === $clientId1 && $message === $message1) ||
                    ($clientId === $clientId2 && $message === $message2)
                );
            });

        $this->repository->handleMessage($message1);
        $this->repository->handleMessage($message2);
    }

    public function test_unregister_one_token_keeps_others_active(): void
    {
        $this->transportFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->transport);

        $token1 = 'keep-active';
        $token2 = 'to-remove';

        $this->repository->registerToken($token1, 'client-1');
        $this->repository->registerToken($token2, 'client-2');

        $this->assertTrue($this->repository->isTokenActive($token1));
        $this->assertTrue($this->repository->isTokenActive($token2));

        $this->repository->unregisterToken($token2);

        $this->assertTrue($this->repository->isTokenActive($token1));
        $this->assertFalse($this->repository->isTokenActive($token2));

        $activeTokens = $this->repository->getActiveTokens();
        $this->assertCount(1, $activeTokens);
        $this->assertContains($token1, $activeTokens);
    }
}