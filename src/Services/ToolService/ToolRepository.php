<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use InvalidArgumentException;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Manages the registration and retrieval of tools available to the MCP server.
 * Tools must implement the ToolInterface.
 *
 * @see [https://modelcontextprotocol.io/docs/concepts/tools](https://modelcontextprotocol.io/docs/concepts/tools)
 */
class ToolRepository
{
    /**
     * Holds the registered tool instances, keyed by their name.
     *
     * @var array<string, ToolInterface>
     */
    protected array $tools = [];

    /**
     * The Symfony container.
     */
    protected ContainerInterface $container;

    /**
     * Constructor.
     *
     * @param  ContainerInterface  $container  The Symfony service container. If null, it resolves from the facade.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        if ($tools = $container->getParameter('klp_mcp_server.tools')) {
            $this->registerMany($tools);
        }
    }

    /**
     * Registers multiple tools at once.
     *
     * @param  array<string|ToolInterface>  $tools  An array of tool class strings or ToolInterface instances.
     * @return $this The current ToolRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If a tool does not implement ToolInterface.
     */
    public function registerMany(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }

        return $this;
    }

    /**
     * Registers a single tool.
     * If a class string is provided, it resolves the tool from the container.
     *
     * @param  string|ToolInterface  $tool  The tool class string or a ToolInterface instance.
     * @return $this The current ToolRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If the provided $tool is not a string or ToolInterface, or if the resolved object does not implement ToolInterface.
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function register(string|ToolInterface $tool): self
    {
        if (is_string($tool)) {
            $tool = $this->container->get($tool);
        }

        if (! $tool instanceof ToolInterface) {
            throw new InvalidArgumentException('Tool must implement the '.ToolInterface::class);
        }

        $this->tools[$tool->getName()] = $tool;

        return $this;
    }

    /**
     * Retrieves all registered tools.
     *
     * @return array<string, ToolInterface> An array of registered tool instances, keyed by their name.
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Retrieves a specific tool by its name.
     *
     * @param  string  $name  The name of the tool to retrieve.
     * @return ToolInterface|null The tool instance if found, otherwise null.
     */
    public function getTool(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Generates an array of schemas for all registered tools, suitable for the MCP capabilities response.
     * Includes name, description, inputSchema, and optional annotations for each tool.
     *
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>, annotations?: array<string, mixed>}> An array of tool schemas.
     */
    public function getToolSchemas(): array
    {
        $schemas = [];
        foreach ($this->tools as $tool) {
            $injectArray = [];
            if (empty($tool->getInputSchema())) {
                // inputSchema cannot be empty, set a default value.
                $injectArray['inputSchema'] = [
                    'type' => 'object',
                    'properties' => new stdClass,
                    'required' => [],
                ];
            }

            $schemas[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
                'annotations' => $tool->getAnnotations()->toArray(),
                ...$injectArray,
            ];
        }

        return $schemas;
    }
}
