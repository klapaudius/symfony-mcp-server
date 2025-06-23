<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService;

use InvalidArgumentException;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Small]
class PromptRepositoryTest extends TestCase
{
    private ContainerInterface $container;

    private PromptRepository $repository;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->container->method('getParameter')
            ->with('klp_mcp_server.prompts')
            ->willReturn([]);

        $this->repository = new PromptRepository($this->container);
    }

    public function test_register_prompt_instance(): void
    {
        $prompt = $this->createMock(PromptInterface::class);
        $prompt->method('getName')->willReturn('test-prompt');

        $result = $this->repository->register($prompt);

        $this->assertSame($this->repository, $result);
        $this->assertSame($prompt, $this->repository->getPrompt('test-prompt'));
    }

    public function test_register_prompt_class_string(): void
    {
        $prompt = $this->createMock(PromptInterface::class);
        $prompt->method('getName')->willReturn('test-prompt');

        $this->container->expects($this->once())
            ->method('get')
            ->with('App\\Prompts\\TestPrompt')
            ->willReturn($prompt);

        $result = $this->repository->register('App\\Prompts\\TestPrompt');

        $this->assertSame($this->repository, $result);
        $this->assertSame($prompt, $this->repository->getPrompt('test-prompt'));
    }

    public function test_register_throws_exception_for_invalid_prompt(): void
    {
        $invalidPrompt = new \stdClass;

        $this->container->expects($this->once())
            ->method('get')
            ->with('InvalidClass')
            ->willReturn($invalidPrompt);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt must implement the '.PromptInterface::class);

        $this->repository->register('InvalidClass');
    }

    public function test_register_many(): void
    {
        $prompt1 = $this->createMock(PromptInterface::class);
        $prompt1->method('getName')->willReturn('prompt1');

        $prompt2 = $this->createMock(PromptInterface::class);
        $prompt2->method('getName')->willReturn('prompt2');

        $result = $this->repository->registerMany([$prompt1, $prompt2]);

        $this->assertSame($this->repository, $result);
        $this->assertSame($prompt1, $this->repository->getPrompt('prompt1'));
        $this->assertSame($prompt2, $this->repository->getPrompt('prompt2'));
    }

    public function test_get_prompts(): void
    {
        $prompt1 = $this->createMock(PromptInterface::class);
        $prompt1->method('getName')->willReturn('prompt1');

        $prompt2 = $this->createMock(PromptInterface::class);
        $prompt2->method('getName')->willReturn('prompt2');

        $this->repository->registerMany([$prompt1, $prompt2]);

        $prompts = $this->repository->getPrompts();

        $this->assertCount(2, $prompts);
        $this->assertArrayHasKey('prompt1', $prompts);
        $this->assertArrayHasKey('prompt2', $prompts);
        $this->assertSame($prompt1, $prompts['prompt1']);
        $this->assertSame($prompt2, $prompts['prompt2']);
    }

    public function test_get_prompt_returns_null_for_unknown_prompt(): void
    {
        $this->assertNull($this->repository->getPrompt('unknown'));
    }

    public function test_get_prompt_schemas(): void
    {
        $prompt1 = $this->createMock(PromptInterface::class);
        $prompt1->method('getName')->willReturn('prompt1');
        $prompt1->method('getDescription')->willReturn('Description 1');
        $prompt1->method('getArguments')->willReturn([
            ['name' => 'arg1', 'description' => 'Argument 1', 'required' => true],
        ]);

        $prompt2 = $this->createMock(PromptInterface::class);
        $prompt2->method('getName')->willReturn('prompt2');
        $prompt2->method('getDescription')->willReturn('Description 2');
        $prompt2->method('getArguments')->willReturn([]);

        $this->repository->registerMany([$prompt1, $prompt2]);

        $schemas = $this->repository->getPromptSchemas();

        $this->assertCount(2, $schemas);

        $this->assertEquals([
            'name' => 'prompt1',
            'description' => 'Description 1',
            'arguments' => [
                ['name' => 'arg1', 'description' => 'Argument 1', 'required' => true],
            ],
        ], $schemas[0]);

        $this->assertEquals([
            'name' => 'prompt2',
            'description' => 'Description 2',
        ], $schemas[1]);
    }

    public function test_constructor_loads_prompts_from_container(): void
    {
        $prompt = $this->createMock(PromptInterface::class);
        $prompt->method('getName')->willReturn('test-prompt');

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.prompts')
            ->willReturn(['App\\Prompts\\TestPrompt']);

        $container->expects($this->once())
            ->method('get')
            ->with('App\\Prompts\\TestPrompt')
            ->willReturn($prompt);

        $repository = new PromptRepository($container);

        $this->assertSame($prompt, $repository->getPrompt('test-prompt'));
    }
}
