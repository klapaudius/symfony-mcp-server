<?php

namespace KLP\KlpMcpServer\Tests\Services\PromptService;

use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptProviderInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Small]
class PromptProviderTest extends TestCase
{
    private ContainerInterface|MockObject $container;

    private PromptRepository $promptRepository;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->promptRepository = new PromptRepository($this->container);
    }

    /**
     * Tests that registerProvider() correctly registers prompts from a PromptProviderInterface.
     *
     * Verifies that the method calls getPrompts() on the provider and registers each
     * returned prompt with the repository.
     */
    public function test_register_provider_registers_prompts_from_provider(): void
    {
        $prompt1 = $this->createMock(PromptInterface::class);
        $prompt2 = $this->createMock(PromptInterface::class);

        $prompt1->method('getName')->willReturn('provider_prompt1');
        $prompt2->method('getName')->willReturn('provider_prompt2');

        $provider = $this->createMock(PromptProviderInterface::class);
        $provider->expects($this->once())
            ->method('getPrompts')
            ->willReturn([$prompt1, $prompt2]);

        $this->promptRepository->registerProvider($provider);

        $prompts = $this->promptRepository->getPrompts();

        $this->assertCount(2, $prompts);
        $this->assertSame($prompt1, $prompts['provider_prompt1']);
        $this->assertSame($prompt2, $prompts['provider_prompt2']);
    }

    /**
     * Tests that registerProvider() works with prompt class names from provider.
     *
     * Verifies that when a provider returns prompt class names (strings),
     * they are correctly resolved from the container and registered.
     */
    public function test_register_provider_with_prompt_class_names(): void
    {
        $prompt1 = $this->createMock(PromptInterface::class);
        $prompt2 = $this->createMock(PromptInterface::class);

        $prompt1->method('getName')->willReturn('prompt_from_class1');
        $prompt2->method('getName')->willReturn('prompt_from_class2');

        $this->container
            ->method('get')
            ->willReturnMap([
                ['PromptClass1', $prompt1],
                ['PromptClass2', $prompt2],
            ]);

        $provider = $this->createMock(PromptProviderInterface::class);
        $provider->expects($this->once())
            ->method('getPrompts')
            ->willReturn(['PromptClass1', 'PromptClass2']);

        $this->promptRepository->registerProvider($provider);

        $prompts = $this->promptRepository->getPrompts();

        $this->assertCount(2, $prompts);
        $this->assertSame($prompt1, $prompts['prompt_from_class1']);
        $this->assertSame($prompt2, $prompts['prompt_from_class2']);
    }

    /**
     * Tests that registerProvider() can be chained with other registration methods.
     *
     * Verifies that prompts from YAML config, direct registration, and providers
     * all coexist in the repository.
     */
    public function test_register_provider_works_alongside_other_registration_methods(): void
    {
        $directPrompt = $this->createMock(PromptInterface::class);
        $providerPrompt = $this->createMock(PromptInterface::class);

        $directPrompt->method('getName')->willReturn('direct_prompt');
        $providerPrompt->method('getName')->willReturn('provider_prompt');

        // Register directly
        $this->promptRepository->register($directPrompt);

        // Register via provider
        $provider = $this->createMock(PromptProviderInterface::class);
        $provider->method('getPrompts')->willReturn([$providerPrompt]);
        $this->promptRepository->registerProvider($provider);

        $prompts = $this->promptRepository->getPrompts();

        $this->assertCount(2, $prompts);
        $this->assertSame($directPrompt, $prompts['direct_prompt']);
        $this->assertSame($providerPrompt, $prompts['provider_prompt']);
    }

    /**
     * Tests that registerProvider() handles empty prompt list from provider.
     *
     * Verifies that when a provider returns an empty array, it doesn't cause errors.
     */
    public function test_register_provider_handles_empty_prompt_list(): void
    {
        $provider = $this->createMock(PromptProviderInterface::class);
        $provider->expects($this->once())
            ->method('getPrompts')
            ->willReturn([]);

        $this->promptRepository->registerProvider($provider);

        $prompts = $this->promptRepository->getPrompts();

        $this->assertCount(0, $prompts);
    }
}
