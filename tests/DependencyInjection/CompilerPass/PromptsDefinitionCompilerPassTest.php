<?php

namespace KLP\KlpMcpServer\Tests\DependencyInjection\CompilerPass;

use KLP\KlpMcpServer\DependencyInjection\CompilerPass\PromptsDefinitionCompilerPass;
use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[Small]
class PromptsDefinitionCompilerPassTest extends TestCase
{
    private PromptsDefinitionCompilerPass $compilerPass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->compilerPass = new PromptsDefinitionCompilerPass;
        $this->container = new ContainerBuilder;

        // Set up required definitions
        $serverDefinition = new Definition('ServerClass');
        $this->container->setDefinition('klp_mcp_server.server', $serverDefinition);

        $promptRepositoryDefinition = new Definition('PromptRepositoryClass');
        $this->container->setDefinition('klp_mcp_server.prompt_repository', $promptRepositoryDefinition);
    }

    public function test_process_registers_prompts_as_services(): void
    {
        $promptClass1 = TestPrompt1::class;
        $promptClass2 = TestPrompt2::class;

        $this->container->setParameter('klp_mcp_server.prompts', [$promptClass1, $promptClass2]);

        $this->compilerPass->process($this->container);

        // Check that prompt services were registered
        $this->assertTrue($this->container->has($promptClass1));
        $this->assertTrue($this->container->has($promptClass2));

        // Check that services are configured correctly
        $definition1 = $this->container->getDefinition($promptClass1);
        $this->assertTrue($definition1->isAutowired());
        $this->assertTrue($definition1->isPublic());
        $this->assertTrue($definition1->hasTag('klp_mcp_server.prompt'));

        $definition2 = $this->container->getDefinition($promptClass2);
        $this->assertTrue($definition2->isAutowired());
        $this->assertTrue($definition2->isPublic());
        $this->assertTrue($definition2->hasTag('klp_mcp_server.prompt'));
    }

    public function test_process_skips_already_defined_services(): void
    {
        $promptClass = TestPrompt1::class;

        // Pre-define the service
        $existingDefinition = new Definition($promptClass);
        $existingDefinition->setAutowired(false); // Different from what compiler pass would set
        $this->container->setDefinition($promptClass, $existingDefinition);

        $this->container->setParameter('klp_mcp_server.prompts', [$promptClass]);

        $this->compilerPass->process($this->container);

        // Check that the existing definition was not modified
        $definition = $this->container->getDefinition($promptClass);
        $this->assertFalse($definition->isAutowired());
    }

    public function test_process_skips_non_existent_classes(): void
    {
        $nonExistentClass = 'App\\Prompts\\NonExistentPrompt';

        $this->container->setParameter('klp_mcp_server.prompts', [$nonExistentClass]);

        $this->compilerPass->process($this->container);

        // Check that the service was not registered
        $this->assertFalse($this->container->has($nonExistentClass));
    }

    public function test_process_adds_register_prompt_repository_method_call(): void
    {
        $this->container->setParameter('klp_mcp_server.prompts', []);

        $this->compilerPass->process($this->container);

        $serverDefinition = $this->container->getDefinition('klp_mcp_server.server');
        $methodCalls = $serverDefinition->getMethodCalls();

        // Check that registerPromptRepository method call was added
        $this->assertCount(1, $methodCalls);
        $this->assertEquals('registerPromptRepository', $methodCalls[0][0]);
        $this->assertCount(1, $methodCalls[0][1]);
        // The argument is a Definition object for the prompt repository
        $this->assertSame($this->container->getDefinition('klp_mcp_server.prompt_repository'), $methodCalls[0][1][0]);
    }

    public function test_process_with_multiple_prompts(): void
    {
        $promptClasses = [
            TestPrompt1::class,
            TestPrompt2::class,
            TestPrompt3::class,
        ];

        $this->container->setParameter('klp_mcp_server.prompts', $promptClasses);

        $this->compilerPass->process($this->container);

        // Check all prompts were registered
        foreach ($promptClasses as $promptClass) {
            $this->assertTrue($this->container->has($promptClass));
            $definition = $this->container->getDefinition($promptClass);
            $this->assertTrue($definition->isAutowired());
            $this->assertTrue($definition->isPublic());
            $this->assertTrue($definition->hasTag('klp_mcp_server.prompt'));
        }
    }
}

// Test prompt classes
class TestPrompt1 implements PromptInterface
{
    public function getName(): string
    {
        return 'test-prompt-1';
    }

    public function getDescription(): string
    {
        return 'Test prompt 1';
    }

    public function getArguments(): array
    {
        return [];
    }

    public function getMessages(): CollectionPromptMessage
    {
        return (new CollectionPromptMessage())
            ->addMessage(new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Test message 1'));
    }
}

class TestPrompt2 implements PromptInterface
{
    public function getName(): string
    {
        return 'test-prompt-2';
    }

    public function getDescription(): string
    {
        return 'Test prompt 2';
    }

    public function getArguments(): array
    {
        return [];
    }

    public function getMessages(): CollectionPromptMessage
    {
        return (new CollectionPromptMessage())
            ->addMessage(new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Test message 2'));
    }
}

class TestPrompt3 implements PromptInterface
{
    public function getName(): string
    {
        return 'test-prompt-3';
    }

    public function getDescription(): string
    {
        return 'Test prompt 3';
    }

    public function getArguments(): array
    {
        return [];
    }

    public function getMessages(): CollectionPromptMessage
    {
        return (new CollectionPromptMessage())
            ->addMessage(new TextPromptMessage(PromptMessageInterface::ROLE_USER, 'Test message 3'));
    }
}
