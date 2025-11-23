<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\PromptService\Examples;

use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptProviderInterface;

/**
 * Example PromptProvider demonstrating dynamic prompt registration.
 *
 * RECOMMENDED APPROACH: Inject prompt instances via constructor.
 * This approach provides better performance and does not require prompts to be public services.
 *
 * @example Basic usage with injected prompt instances (RECOMMENDED):
 * ```php
 * class MyPromptProvider implements PromptProviderInterface
 * {
 *     public function __construct(
 *         private readonly HelloWorldPrompt $helloPrompt,
 *         private readonly CodeReviewPrompt $reviewPrompt,
 *     ) {}
 *
 *     public function getPrompts(): iterable
 *     {
 *         return [$this->helloPrompt, $this->reviewPrompt];
 *     }
 * }
 * ```
 *
 * ALTERNATIVE APPROACH: Return prompt class names.
 * WARNING: This requires prompts to be registered as public services.
 *
 * @example Returning class names (NOT RECOMMENDED - requires public services):
 * ```php
 * public function getPrompts(): iterable
 * {
 *     return [
 *         HelloWorldPrompt::class,  // Must be public service!
 *         CodeReviewPrompt::class,
 *     ];
 * }
 *
 * // Required in config/services.yaml:
 * services:
 *     App\MCP\Prompts\:
 *         resource: '../src/MCP/Prompts/*'
 *         public: true  # Required for class name approach
 * ```
 *
 * Why prefer injecting prompt instances?
 * - ✅ No need to make services public (better encapsulation)
 * - ✅ Better performance (prompts instantiated once during compilation)
 * - ✅ Type safety with constructor injection
 * - ✅ Follows Symfony best practices
 *
 * @example Registration in services.yaml:
 * ```yaml
 * services:
 *     App\Service\MyCustomPromptProvider:
 *         autowire: true
 *         autoconfigure: true  # Automatically tags with 'klp_mcp_server.prompt_provider'
 * ```
 *
 * @codeCoverageIgnore
 */
class ExamplePromptProvider implements PromptProviderInterface
{
    /**
     * Constructor demonstrating dependency injection.
     *
     * RECOMMENDED: Inject prompt instances directly for better performance
     * and to avoid requiring public service declarations.
     *
     * @example With injected prompt instances:
     * ```php
     * public function __construct(
     *     private readonly HelloWorldPrompt $helloPrompt,
     *     private readonly CodeReviewPrompt $reviewPrompt,
     *     private readonly string $environment,
     * ) {}
     * ```
     */
    public function __construct(
        private readonly HelloWorldPrompt $helloPrompt,
        private readonly CodeReviewPrompt $reviewPrompt,
    ) {
        // Prompts are injected and ready to use
    }

    /**
     * Returns the prompts to be registered.
     *
     * RECOMMENDED: Return injected prompt instances (not class names).
     *
     * This method is called at runtime when the PromptRepository is instantiated.
     * Prompts returned here will be registered with the MCP server.
     *
     * @return iterable<PromptInterface> Array of prompt instances
     */
    public function getPrompts(): iterable
    {
        // RECOMMENDED: Return prompt instances injected in constructor
        return [
            $this->helloPrompt,
            $this->reviewPrompt,
        ];

        // NOT RECOMMENDED: Returning class names requires public services
        // return [
        //     HelloWorldPrompt::class,  // ⚠️ Must be public service
        //     CodeReviewPrompt::class,
        // ];

        // Example: Conditional prompt loading with injected instances
        // $prompts = [$this->helloPrompt];
        // if ($this->environment === 'dev') {
        //     $prompts[] = $this->debugPrompt;
        // }
        // return $prompts;

        // Example: Database-driven prompt selection with injected instances
        // $enabledPrompts = $this->entityManager
        //     ->getRepository(PromptConfiguration::class)
        //     ->findBy(['enabled' => true]);
        //
        // $prompts = [];
        // foreach ($enabledPrompts as $config) {
        //     match ($config->getName()) {
        //         'hello-world' => $prompts[] = $this->helloPrompt,
        //         'code-review' => $prompts[] = $this->reviewPrompt,
        //         default => null,
        //     };
        // }
        // return $prompts;
    }
}
