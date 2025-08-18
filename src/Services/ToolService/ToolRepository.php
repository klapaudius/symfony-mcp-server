<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use InvalidArgumentException;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Manages the registration and retrieval of tools available to the MCP server.
 * Tools must implement the StreamableToolInterface.
 *
 * @see [https://modelcontextprotocol.io/docs/concepts/tools](https://modelcontextprotocol.io/docs/concepts/tools)
 */
class ToolRepository
{
    /**
     * Holds the registered tool instances, keyed by their name.
     *
     * @var array<string, StreamableToolInterface>
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
     * @param  array<string|StreamableToolInterface>  $tools  An array of tool class strings or StreamableToolInterface instances.
     * @return $this The current ToolRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If a tool does not implement StreamableToolInterface.
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
     * @param  string|StreamableToolInterface  $tool  The tool class string or a StreamableToolInterface instance.
     * @return $this The current ToolRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If the provided $tool is not a string or StreamableToolInterface, or if the resolved object does not implement StreamableToolInterface.
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function register(string|StreamableToolInterface $tool): self
    {
        if (is_string($tool)) {
            $tool = $this->container->get($tool);
        }

        if (! $tool instanceof BaseToolInterface) {
            throw new InvalidArgumentException('Tool must implement the '.StreamableToolInterface::class);
        }

        $this->tools[$tool->getName()] = $tool;

        return $this;
    }

    /**
     * Retrieves all registered tools.
     *
     * @return array<string, StreamableToolInterface> An array of registered tool instances, keyed by their name.
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Retrieves a specific tool by its name.
     *
     * @param  string  $name  The name of the tool to retrieve.
     * @return StreamableToolInterface|BaseToolInterface|null The tool instance if found, otherwise null.
     *
     * @todo Set return type to ?StreamableToolInterface on v2.0.0.
     */
    public function getTool(string $name): StreamableToolInterface|BaseToolInterface|null
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
            $inputSchema = $tool->getInputSchema();
            if (method_exists($tool, 'getOutputSchema')
                && $outputSchema = $tool->getOutputSchema()) {
                $injectArray['outputSchema'] = $outputSchema->asArray();
            }

            $schemas[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $inputSchema instanceof StructuredSchema ? $inputSchema->asArray() : $inputSchema,
                'annotations' => $tool->getAnnotations()->toArray(),
                ...$injectArray,
            ];
        }

        return $schemas;
    }
}
