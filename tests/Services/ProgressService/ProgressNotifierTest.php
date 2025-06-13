<?php

namespace KLP\KlpMcpServer\Tests\Services\ProgressService;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifier;
use KLP\KlpMcpServer\Services\ProgressService\ProgressTokenException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ProgressNotifierTest extends TestCase
{
    private array $capturedNotifications = [];

    protected function setUp(): void
    {
        $this->capturedNotifications = [];
    }

    private function createProgressHandler(): callable
    {
        return function (array $notification) {
            $this->capturedNotifications[] = $notification;
        };
    }

    public function test_constructor_sets_correct_properties(): void
    {
        $progressToken = 'test-token-123';
        $handler = $this->createProgressHandler();
        
        $notifier = new ProgressNotifier($progressToken, $handler);

        $this->assertInstanceOf(ProgressNotifier::class, $notifier);
    }

    public function test_send_progress_with_basic_notification(): void
    {
        $progressToken = 'test-token-123';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(50);

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals('2.0', $notification['jsonrpc']);
        $this->assertEquals('notifications/progress', $notification['method']);
        $this->assertEquals($progressToken, $notification['params']['progressToken']);
        $this->assertEquals(50, $notification['params']['progress']);
        $this->assertArrayNotHasKey('total', $notification['params']);
        $this->assertArrayNotHasKey('message', $notification['params']);
    }

    public function test_send_progress_with_total_and_message(): void
    {
        $progressToken = 'test-token-456';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(25, 100, 'Processing files...');

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals('2.0', $notification['jsonrpc']);
        $this->assertEquals('notifications/progress', $notification['method']);
        $this->assertEquals($progressToken, $notification['params']['progressToken']);
        $this->assertEquals(25, $notification['params']['progress']);
        $this->assertEquals(100, $notification['params']['total']);
        $this->assertEquals('Processing files...', $notification['params']['message']);
    }

    public function test_send_progress_with_integer_token(): void
    {
        $progressToken = 12345;
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(10);

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals($progressToken, $notification['params']['progressToken']);
        $this->assertEquals(10, $notification['params']['progress']);
    }

    public function test_send_progress_with_float_values(): void
    {
        $progressToken = 'test-float';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(25, 100, 'Half way through...');

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals(25, $notification['params']['progress']);
        $this->assertEquals(100, $notification['params']['total']);
        $this->assertEquals('Half way through...', $notification['params']['message']);
    }

    public function test_send_progress_multiple_times_with_increasing_values(): void
    {
        $progressToken = 'test-multiple';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(10, 100, 'Starting...');
        $notifier->sendProgress(50, 100, 'Half done...');
        $notifier->sendProgress(100, 100, 'Complete!');

        $this->assertCount(3, $this->capturedNotifications);
        
        $this->assertEquals(10, $this->capturedNotifications[0]['params']['progress']);
        $this->assertEquals('Starting...', $this->capturedNotifications[0]['params']['message']);
        
        $this->assertEquals(50, $this->capturedNotifications[1]['params']['progress']);
        $this->assertEquals('Half done...', $this->capturedNotifications[1]['params']['message']);
        
        $this->assertEquals(100, $this->capturedNotifications[2]['params']['progress']);
        $this->assertEquals('Complete!', $this->capturedNotifications[2]['params']['message']);
    }

    public function test_send_progress_throws_exception_when_progress_decreases(): void
    {
        $progressToken = 'test-decrease';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(50);

        $this->expectException(ProgressTokenException::class);
        $this->expectExceptionMessage('Progress value must increase with each notification. Current: 30, Last: 50');

        $notifier->sendProgress(30);
    }

    public function test_send_progress_throws_exception_when_progress_stays_same(): void
    {
        $progressToken = 'test-same';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(25);

        $this->expectException(ProgressTokenException::class);
        $this->expectExceptionMessage('Progress value must increase with each notification. Current: 25, Last: 25');

        $notifier->sendProgress(25);
    }

    public function test_send_progress_with_zero_initial_value(): void
    {
        $progressToken = 'test-zero';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(0, 100, 'Starting from zero...');

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals(0, $notification['params']['progress']);
        $this->assertEquals(100, $notification['params']['total']);
        $this->assertEquals('Starting from zero...', $notification['params']['message']);
    }

    public function test_send_progress_with_negative_progress_fails_immediately(): void
    {
        $progressToken = 'test-negative';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $this->expectException(ProgressTokenException::class);
        $this->expectExceptionMessage('Progress value must increase with each notification. Current: -10, Last: -1');

        $notifier->sendProgress(-10);
    }

    public function test_send_progress_with_only_total(): void
    {
        $progressToken = 'test-total-only';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(50, 200);

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals(50, $notification['params']['progress']);
        $this->assertEquals(200, $notification['params']['total']);
        $this->assertArrayNotHasKey('message', $notification['params']);
    }

    public function test_send_progress_with_only_message(): void
    {
        $progressToken = 'test-message-only';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(75, null, 'Almost there!');

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals(75, $notification['params']['progress']);
        $this->assertEquals('Almost there!', $notification['params']['message']);
        $this->assertArrayNotHasKey('total', $notification['params']);
    }

    public function test_send_progress_with_empty_message(): void
    {
        $progressToken = 'test-empty-message';
        $handler = $this->createProgressHandler();
        $notifier = new ProgressNotifier($progressToken, $handler);

        $notifier->sendProgress(10, 100, '');

        $this->assertCount(1, $this->capturedNotifications);
        $notification = $this->capturedNotifications[0];
        
        $this->assertEquals(10, $notification['params']['progress']);
        $this->assertEquals(100, $notification['params']['total']);
        $this->assertEquals('', $notification['params']['message']);
    }
}