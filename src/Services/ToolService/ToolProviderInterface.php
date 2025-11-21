<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ToolService;

/**
 * Interface for dynamic tool providers.
 *
 * Implement this interface to dynamically provide tools to the MCP server
 * based on custom logic (e.g., database queries, environment configuration,
 * feature flags, or any other runtime conditions).
 *
 * All classes implementing this interface will be automatically discovered
 * and registered by the ToolsDefinitionCompilerPass during container compilation.
 *
 * @example
 * ```php
 * class DatabaseToolProvider implements ToolProviderInterface
 * {
 *     public function __construct(
 *         private EntityManagerInterface $entityManager
 *     ) {}
 *
 *     public function getTools(): iterable
 *     {
 *         $tools = $this->entityManager
 *             ->getRepository(CustomTool::class)
 *             ->findBy(['enabled' => true]);
 *
 *         return array_map(
 *             fn($tool) => $tool->getToolClass(),
 *             $tools
 *         );
 *     }
 * }
 * ```
 */
interface ToolProviderInterface
{
    /**
     * Returns an iterable collection of tools to register.
     *
     * Each item can be either:
     * - A fully-qualified class name (string) that implements BaseToolInterface
     * - An instance of StreamableToolInterface
     *
     * The returned tools will be registered with the ToolRepository alongside
     * tools defined in the YAML configuration.
     *
     * @return iterable<string|StreamableToolInterface> Array of tool class names or instances
     */
    public function getTools(): iterable;
}
