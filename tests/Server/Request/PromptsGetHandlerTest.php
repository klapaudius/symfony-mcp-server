<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Server\Request\PromptsGetHandler;
use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class PromptsGetHandlerTest extends TestCase
{
    private PromptRepository $promptRepository;

    private PromptsGetHandler $handler;

    protected function setUp(): void
    {
        $this->promptRepository = $this->createMock(PromptRepository::class);
        $this->handler = new PromptsGetHandler($this->promptRepository);
    }

    public function test_is_handle_returns_true_for_prompts_get(): void
    {
        $this->assertTrue($this->handler->isHandle('prompts/get'));
    }

    public function test_is_handle_returns_false_for_other_methods(): void
    {
        $this->assertFalse($this->handler->isHandle('prompts/list'));
        $this->assertFalse($this->handler->isHandle('tools/call'));
        $this->assertFalse($this->handler->isHandle('resources/read'));
    }

    public function test_execute_throws_exception_when_name_not_provided(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Prompt name is required');

        $this->handler->execute('prompts/get', 'client-123', 'msg-456', []);
    }

    public function test_execute_throws_exception_when_name_is_not_string(): void
    {
        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Prompt name is required');

        $this->handler->execute('prompts/get', 'client-123', 'msg-456', ['name' => 123]);
    }

    public function test_execute_throws_exception_when_prompt_not_found(): void
    {
        $this->promptRepository->expects($this->once())
            ->method('getPrompt')
            ->with('unknown-prompt')
            ->willReturn(null);

        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage("Prompt 'unknown-prompt' not found");

        $this->handler->execute('prompts/get', 'client-123', 'msg-456', ['name' => 'unknown-prompt']);
    }

    public function test_execute_returns_prompt_messages_without_arguments(): void
    {
        $collection = new CollectionPromptMessage;
        $collection->addMessage(new TextPromptMessage('user', 'Hello'));

        $prompt = $this->createMock(PromptInterface::class);
        $prompt->expects($this->once())
            ->method('getDescription')
            ->willReturn('Test prompt description');
        $prompt->expects($this->once())
            ->method('getMessages')
            ->willReturn($collection);

        $this->promptRepository->expects($this->once())
            ->method('getPrompt')
            ->with('test-prompt')
            ->willReturn($prompt);

        $result = $this->handler->execute('prompts/get', 'client-123', 'msg-456', ['name' => 'test-prompt']);

        $this->assertArrayHasKey('description', $result);
        $this->assertEquals('Test prompt description', $result['description']);
        $this->assertArrayHasKey('messages', $result);
        $this->assertEquals([
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello']],
        ], $result['messages']);
    }
}
