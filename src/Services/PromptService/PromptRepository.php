<?php

namespace KLP\KlpMcpServer\Services\PromptService;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Manages the registration and retrieval of prompts available to the MCP server.
 * Prompts must implement the PromptInterface.
 *
 * @see [https://modelcontextprotocol.io/docs/concepts/prompts](https://modelcontextprotocol.io/docs/concepts/prompts)
 */
class PromptRepository
{
    /**
     * Holds the registered prompt instances, keyed by their name.
     *
     * @var array<string, PromptInterface>
     */
    protected array $prompts = [];

    /**
     * The Symfony container.
     */
    protected ContainerInterface $container;

    /**
     * The logger instance.
     */
    protected ?LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param  ContainerInterface  $container  The Symfony service container.
     * @param  LoggerInterface|null  $logger  Optional logger instance for debugging prompt registration.
     */
    public function __construct(ContainerInterface $container, ?LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->logger?->debug('PromptRepository initialized');

        if ($prompts = $container->getParameter('klp_mcp_server.prompts')) {
            $this->logger?->debug('Registering prompts from configuration', ['prompts' => $prompts]);
            $this->registerMany($prompts);
        }
    }

    /**
     * Registers multiple prompts at once.
     *
     * @param  array<string|PromptInterface>  $prompts  An array of prompt class strings or PromptInterface instances.
     * @return $this The current PromptRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If a prompt does not implement PromptInterface.
     */
    public function registerMany(array $prompts): self
    {
        foreach ($prompts as $prompt) {
            $this->register($prompt);
        }

        return $this;
    }

    /**
     * Registers prompts from a PromptProviderInterface.
     *
     * This method is called by the PromptsDefinitionCompilerPass for each
     * discovered prompt provider. It retrieves prompts from the provider and
     * registers them with the repository.
     *
     * @param  PromptProviderInterface  $provider  The prompt provider instance.
     * @return $this The current PromptRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If a prompt does not implement PromptInterface.
     */
    public function registerProvider(PromptProviderInterface $provider): self
    {
        $providerClass = get_class($provider);

        $this->logger?->debug('Registering prompts from PromptProvider', [
            'provider' => $providerClass,
        ]);

        $prompts = $provider->getPrompts();
        $promptCount = is_countable($prompts) ? count($prompts) : 0;

        foreach ($prompts as $prompt) {
            $this->register($prompt);
        }

        $this->logger?->debug('Successfully registered prompts from PromptProvider', [
            'provider' => $providerClass,
            'prompt_count' => $promptCount,
        ]);

        return $this;
    }

    /**
     * Registers a single prompt.
     * If a class string is provided, it resolves the prompt from the container.
     *
     * @param  string|PromptInterface  $prompt  The prompt class string or a PromptInterface instance.
     * @return $this The current PromptRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If the provided $prompt is not a string or PromptInterface, or if the resolved object does not implement PromptInterface.
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function register(string|PromptInterface $prompt): self
    {
        if (is_string($prompt)) {
            $prompt = $this->container->get($prompt);
        }

        if (! $prompt instanceof PromptInterface) {
            throw new InvalidArgumentException('Prompt must implement the '.PromptInterface::class);
        }

        $this->prompts[$prompt->getName()] = $prompt;

        return $this;
    }

    /**
     * Retrieves all registered prompts.
     *
     * @return array<string, PromptInterface> An array of registered prompt instances, keyed by their name.
     */
    public function getPrompts(): array
    {
        return $this->prompts;
    }

    /**
     * Retrieves a specific prompt by its name.
     *
     * @param  string  $name  The name of the prompt to retrieve.
     * @return PromptInterface|null The prompt instance if found, otherwise null.
     */
    public function getPrompt(string $name): ?PromptInterface
    {
        return $this->prompts[$name] ?? null;
    }

    /**
     * Generates an array of schemas for all registered prompts, suitable for the MCP capabilities response.
     * Includes name, description, and arguments for each prompt.
     *
     * @return array<int, array{name: string, description: string, arguments?: array<int, array{name: string, description?: string, required?: bool}>}> An array of prompt schemas.
     */
    public function getPromptSchemas(): array
    {
        $schemas = [];
        foreach ($this->prompts as $prompt) {
            $schema = [
                'name' => $prompt->getName(),
                'description' => $prompt->getDescription(),
            ];

            $arguments = $prompt->getArguments();
            if (! empty($arguments)) {
                $schema['arguments'] = $arguments;
            }

            $schemas[] = $schema;
        }

        return $schemas;
    }
}
