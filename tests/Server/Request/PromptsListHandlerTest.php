<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Server\Request\PromptsListHandler;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class PromptsListHandlerTest extends TestCase
{
    private PromptRepository $promptRepository;
    private PromptsListHandler $handler;

    protected function setUp(): void
    {
        $this->promptRepository = $this->createMock(PromptRepository::class);
        $this->handler = new PromptsListHandler($this->promptRepository);
    }

    public function test_is_handle_returns_true_for_prompts_list(): void
    {
        $this->assertTrue($this->handler->isHandle('prompts/list'));
    }

    public function test_is_handle_returns_false_for_other_methods(): void
    {
        $this->assertFalse($this->handler->isHandle('prompts/get'));
        $this->assertFalse($this->handler->isHandle('tools/list'));
        $this->assertFalse($this->handler->isHandle('resources/list'));
    }

    public function test_execute_returns_prompt_schemas(): void
    {
        $schemas = [
            [
                'name' => 'prompt1',
                'description' => 'Test prompt 1',
                'arguments' => [
                    ['name' => 'arg1', 'description' => 'Argument 1', 'required' => true]
                ]
            ],
            [
                'name' => 'prompt2',
                'description' => 'Test prompt 2'
            ]
        ];

        $this->promptRepository->expects($this->once())
            ->method('getPromptSchemas')
            ->willReturn($schemas);

        $result = $this->handler->execute('prompts/list', 'client-123', 'msg-456');

        $this->assertArrayHasKey('prompts', $result);
        $this->assertEquals($schemas, $result['prompts']);
    }

    public function test_execute_returns_empty_array_when_no_prompts(): void
    {
        $this->promptRepository->expects($this->once())
            ->method('getPromptSchemas')
            ->willReturn([]);

        $result = $this->handler->execute('prompts/list', 'client-123', 'msg-456');

        $this->assertArrayHasKey('prompts', $result);
        $this->assertEquals([], $result['prompts']);
    }
}
