<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use KLP\KlpMcpServer\Services\ToolService\ToolProviderInterface;

/**
 * Example ToolProvider demonstrating dynamic tool registration.
 *
 * RECOMMENDED APPROACH: Inject tool instances via constructor.
 * This approach provides better performance and does not require tools to be public services.
 *
 * @example Basic usage with injected tool instances (RECOMMENDED):
 * ```php
 * class MyToolProvider implements ToolProviderInterface
 * {
 *     public function __construct(
 *         private readonly HelloWorldTool $helloTool,
 *         private readonly VersionCheckTool $versionTool,
 *     ) {}
 *
 *     public function getTools(): iterable
 *     {
 *         return [$this->helloTool, $this->versionTool];
 *     }
 * }
 * ```
 *
 * ALTERNATIVE APPROACH: Return tool class names.
 * WARNING: This requires tools to be registered as public services.
 * @example Returning class names (NOT RECOMMENDED - requires public services):
 * ```php
 * public function getTools(): iterable
 * {
 *     return [
 *         HelloWorldTool::class,  // Must be public service!
 *         VersionCheckTool::class,
 *     ];
 * }
 *
 * // Required in config/services.yaml:
 * services:
 *     App\MCP\Tools\:
 *         resource: '../src/MCP/Tools/*'
 *         public: true  # Required for class name approach
 * ```
 *
 * Why prefer injecting tool instances?
 * - ✅ No need to make services public (better encapsulation)
 * - ✅ Better performance (tools instantiated once during compilation)
 * - ✅ Type safety with constructor injection
 * - ✅ Follows Symfony best practices
 * @example Registration in services.yaml:
 * ```yaml
 * services:
 *     App\Service\MyCustomToolProvider:
 *         autowire: true
 *         autoconfigure: true  # Automatically tags with 'klp_mcp_server.tool_provider'
 * ```
 *
 * @codeCoverageIgnore
 */
class ExampleToolProvider implements ToolProviderInterface
{
    /**
     * Constructor demonstrating dependency injection.
     *
     * RECOMMENDED: Inject tool instances directly for better performance
     * and to avoid requiring public service declarations.
     *
     * @example With injected tool instances:
     * ```php
     * public function __construct(
     *     private readonly HelloWorldTool $helloTool,
     *     private readonly VersionCheckTool $versionTool,
     *     private readonly string $environment,
     * ) {}
     * ```
     */
    public function __construct(
        private readonly HelloWorldTool $helloTool,
        private readonly VersionCheckTool $versionTool,
    ) {
        // Tools are injected and ready to use
    }

    /**
     * Returns the tools to be registered.
     *
     * RECOMMENDED: Return injected tool instances (not class names).
     *
     * This method is called at runtime when the ToolRepository is instantiated.
     * Tools returned here will be registered with the MCP server.
     *
     * @return iterable<StreamableToolInterface> Array of tool instances
     */
    public function getTools(): iterable
    {
        // RECOMMENDED: Return tool instances injected in constructor
        return [
            $this->helloTool,
            $this->versionTool,
        ];

        // NOT RECOMMENDED: Returning class names requires public services
        // return [
        //     HelloWorldTool::class,  // ⚠️ Must be public service
        //     VersionCheckTool::class,
        // ];

        // Example: Conditional tool loading with injected instances
        // $tools = [$this->helloTool];
        // if ($this->environment === 'dev') {
        //     $tools[] = $this->debugTool;
        // }
        // return $tools;

        // Example: Database-driven tool selection with injected instances
        // $enabledTools = $this->entityManager
        //     ->getRepository(ToolConfiguration::class)
        //     ->findBy(['enabled' => true]);
        //
        // $tools = [];
        // foreach ($enabledTools as $config) {
        //     match ($config->getName()) {
        //         'hello' => $tools[] = $this->helloTool,
        //         'version' => $tools[] = $this->versionTool,
        //         default => null,
        //     };
        // }
        // return $tools;
    }
}
