<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\PromptService;

/**
 * Interface for dynamic prompt providers.
 *
 * Implement this interface to dynamically provide prompts to the MCP server
 * based on custom logic (e.g., database queries, environment configuration,
 * feature flags, or any other runtime conditions).
 *
 * All classes implementing this interface will be automatically discovered
 * and registered by the PromptsDefinitionCompilerPass during container compilation.
 *
 * @example
 * ```php
 * class DatabasePromptProvider implements PromptProviderInterface
 * {
 *     public function __construct(
 *         private EntityManagerInterface $entityManager
 *     ) {}
 *
 *     public function getPrompts(): iterable
 *     {
 *         $prompts = $this->entityManager
 *             ->getRepository(CustomPrompt::class)
 *             ->findBy(['enabled' => true]);
 *
 *         return array_map(
 *             fn($prompt) => $prompt->getPromptClass(),
 *             $prompts
 *         );
 *     }
 * }
 * ```
 */
interface PromptProviderInterface
{
    /**
     * Returns an iterable collection of prompts to register.
     *
     * Each item can be either:
     * - A fully-qualified class name (string) that implements PromptInterface
     * - An instance of PromptInterface
     *
     * The returned prompts will be registered with the PromptRepository alongside
     * prompts defined in the YAML configuration.
     *
     * @return iterable<string|PromptInterface> Array of prompt class names or instances
     */
    public function getPrompts(): iterable;
}
