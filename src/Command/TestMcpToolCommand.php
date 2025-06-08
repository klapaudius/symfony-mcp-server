<?php

namespace KLP\KlpMcpServer\Command;

use KLP\KlpMcpServer\Exceptions\TestMcpToolCommandException;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(name: 'mcp:test-tool', description: 'Test an MCP tool with simulated inputs')]
class TestMcpToolCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    private InputInterface $input;

    private SymfonyStyle $io;

    public function displayResult(mixed $result): void
    {
        $this->io->success('Tool executed successfully!');
        $resultText = ['Result:'];
        if (is_array($result) || is_object($result)) {
            $resultText[] = json_encode($result, JSON_PRETTY_PRINT);
        } else {
            $resultText[] = (string) $result;
        }
        $this->io->text($resultText);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tool', InputArgument::OPTIONAL, 'The name or class of the tool to test')
            ->addOption('input', '-i', InputOption::VALUE_OPTIONAL, 'JSON input for the tool')
            ->addOption('list', '-l', InputOption::VALUE_NONE, 'List all available tools')
            ->setDescription('Test an MCP tool with simulated inputs')
            ->setHelp(<<<'EOT'
mcp:test-tool {tool? : The name or class of the tool to test} {--input= : JSON input for the tool} {--list : List all available tools}
EOT
            );
    }

    /**
     * Execute the console command.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input ??= $input;
        $this->io ??= new SymfonyStyle($input, $output);

        // List all tools if --list option is provided
        return $this->input->getOption('list')
            ? $this->listAllTools()
            : $this->testTool();
    }

    private function testTool(): int
    {
        try {
            // Create the tool instance and display its schema
            $tool = $this->getToolInstance();
            $this->displaySchema($tool);

            // Get input data
            $inputData = $this->getInputDataFromOption()
                ?? $this->askForInputData($tool->getInputSchema());
            if ($inputData === null) {
                throw new TestMcpToolCommandException('Invalid input data.');
            }

            // Execute the tool
            $this->io->text([
                'Executing tool with input data:',
                json_encode($inputData, JSON_PRETTY_PRINT),
            ]);
            try {
                $result = $tool->execute($inputData);
                $this->displayResult($result);

                return command::SUCCESS;
            } catch (\Throwable $e) {
                $this->io->error("Error executing tool: {$e->getMessage()}");
                $this->io->text([
                    'Stack trace:',
                    $e->getTraceAsString(),
                ]);

                return Command::FAILURE;
            }
        } catch (TestMcpToolCommandException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Resolves and retrieves an instance of a tool based on the provided identifier
     * from the input or user prompt. The method checks for a matching class name,
     * an exact tool match, or a case-insensitive tool name match from the configured tools.
     *
     * @return StreamableToolInterface Returns the tool instance if found.
     *
     * @throws TestMcpToolCommandException If no tool is specified or if the tool cannot be resolved.
     */
    public function getToolInstance(): StreamableToolInterface
    {
        // Get the tool name from the argument or prompt for it
        $identifier = $this->input->getArgument('tool') ?: $this->askForTool();
        if (! $identifier) {
            throw new TestMcpToolCommandException('No tool specified.');
        }

        $toolInstance = null;
        // First check if the identifier is a direct class name
        if (class_exists($identifier)) {
            $toolInstance = $this->container->get($identifier);
        }

        if (! $toolInstance) {
            // Load all registered tools from config
            $configuredTools = $this->container->getParameter('klp_mcp_server.tools');

            // Check for the class match
            foreach ($configuredTools as $toolClass) {
                $instance = $this->container->get($toolClass);
                if ( // Check for the exact class match
                    str_ends_with($toolClass, "\\{$identifier}")
                    || $toolClass === $identifier
                    // Check for tool name match (case-insensitive)
                    || strtolower($instance->getName()) === strtolower($identifier)
                ) {
                    $toolInstance = $instance;
                    break;
                }
            }
        }

        if ($toolInstance
            && ! ($toolInstance instanceof StreamableToolInterface)) {
            $toolClass = get_class($toolInstance);
            throw new TestMcpToolCommandException("The class '{$toolClass}' does not implement StreamableToolInterface.");
        }

        return $toolInstance ?: throw new TestMcpToolCommandException("Tool '{$identifier}' not found.");
    }

    /**
     * Displays the schema information for a specific tool.
     *
     * @param  StreamableToolInterface  $tool  The tool instance whose schema is to be displayed.
     */
    public function displaySchema(StreamableToolInterface $tool): void
    {
        $toolClass = get_class($tool);
        $this->io->text([
            "Testing tool: {$tool->getName()} ({$toolClass})",
            "Description: {$tool->getDescription()}",
        ]);
        $this->io->newLine();
        // Get input schema
        $this->io->text(array_merge(
            ['Input schema:'],
            $this->getSchemaDisplayMessages($tool->getInputSchema())
        ));
    }

    /**
     * Generates a list of formatted display messages based on the provided schema.
     * The method processes the schema's properties recursively, handling nested objects
     * and arrays to construct a readable representation of the schema details.
     *
     * @param  array  $schema  The schema definition to be parsed.
     * @param  string  $indent  The indentation string used for structuring nested properties.
     * @return array Returns an array of formatted strings representing the schema details.
     */
    protected function getSchemaDisplayMessages(array $schema, string $indent = ''): array
    {
        $messages = [];
        if (is_array($schema['properties'] ?? null)) {
            foreach ($schema['properties'] as $propName => $propSchema) {
                $type = $propSchema['type'] ?? 'any';
                $description = $propSchema['description'] ?? '';
                $required = in_array($propName, $schema['required'] ?? []) ? '(required)' : '(optional)';

                $messages[] = "{$indent}- {$propName}: {$type} {$required}";
                $messages[] = "{$indent}  Description: {$description}";

                // If this is an object with nested properties
                if ($type === 'object' && isset($propSchema['properties'])) {
                    $messages[] = "{$indent}  Properties:";
                    $messages = array_merge($messages, $this->getSchemaDisplayMessages($propSchema, $indent.'    '));
                }

                // If this is an array with items
                if ($type === 'array' && isset($propSchema['items'])) {
                    $this->getArraySchemaDisplay($propSchema, $indent, $messages);
                }
            }
        }

        return $messages;
    }

    private function getArraySchemaDisplay(mixed $propSchema, string $indent, array &$messages): void
    {
        $itemType = $propSchema['items']['type'] ?? 'any';
        $messages[] = "{$indent}  Items: {$itemType}";
        if (isset($propSchema['items']['properties'])) {
            $messages[] = "{$indent}  Item Properties:";
            $messages = array_merge($messages, $this->getSchemaDisplayMessages($propSchema['items'], $indent.'    '));
        }
    }

    public function getInputDataFromOption(): ?array
    {
        // If input is provided as an option, use that
        $inputOption = $this->input->getOption('input');
        if ($inputOption) {
            try {
                $decodedInput = json_decode($inputOption, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TestMcpToolCommandException(json_last_error_msg());
                }

                return $decodedInput;
            } catch (\Throwable $e) {
                $this->io->error("Invalid JSON input: {$e->getMessage()}");
            }
        }

        return null;
    }

    /**
     * Get input data from user.
     */
    public function askForInputData(array $schema): ?array
    {
        $input = [];

        if (! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return $input;
        }

        foreach ($schema['properties'] as $propName => $propSchema) {
            $type = $propSchema['type'] ?? 'string';
            $description = $propSchema['description'] ?? '';
            $required = in_array($propName, $schema['required'] ?? []);

            $this->io->text("Property: {$propName} ({$type}) {$description}");

            if (in_array($type, ['object', 'array'])) {
                $input[$propName] = $this->askForJsonInput($input, $type, $required);
            } elseif ($type === 'boolean') {
                $default = $propSchema['default'] ?? false;
                $input[$propName] = $this->io->confirm('Value (yes/no)', $default);
            } elseif (in_array($type, ['number', 'integer'])) {
                $default = $propSchema['default'] ?? '';
                $input[$propName] = $this->askForNumericInput($default, $type, $required);
            } else {
                // String or other types
                $default = $propSchema['default'] ?? '';
                $input[$propName] = $this->askForStandardInput($default, $required);
            }
            if ($input[$propName] === null
                && ! $required
                && $type !== 'object') {
                unset($input[$propName]);
            }
        }

        return $input;
    }

    /**
     * List all available tools.
     */
    private function listAllTools(): int
    {
        $configuredTools = $this->container->getParameter('klp_mcp_server.tools');

        if (empty($configuredTools)) {
            $this->io->warning('No MCP tools are configured. Add tools in config/package/klp-mcp-server.yaml');

            return Command::SUCCESS;
        }

        $tools = [];

        foreach ($configuredTools as $toolClass) {
            try {
                if (class_exists($toolClass)) {
                    $instance = $this->container->get($toolClass);
                    if ($instance instanceof StreamableToolInterface) {
                        $tools[] = [
                            'name' => $instance->getName(),
                            'class' => $toolClass,
                            'description' => substr($instance->getDescription(), 0, 50),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $this->io->warning("Couldn't load tool class: {$toolClass}");
            }
        }

        $this->io->info('Available MCP Tools:');
        $this->io->table(['Name', 'Class', 'Description'], $tools);

        $this->io->text([
            'To test a specific tool, run:',
            '    php bin/console mcp:test-tool [tool_name]',
            "    php bin/console mcp:test-tool --input='{\"param\":\"value\"}'",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Ask the user to select a tool.
     */
    protected function askForTool(): ?string
    {
        $configuredTools = $this->container->getParameter('klp_mcp_server.tools');

        if (empty($configuredTools)) {
            $this->io->warning('No MCP tools are configured. Add tools in config/package/klp-mcp-server.yaml');

            return null;
        }

        $choices = [];
        $validTools = [];

        foreach ($configuredTools as $toolClass) {
            try {
                if (class_exists($toolClass)) {
                    $instance = $this->container->get($toolClass);
                    if ($instance instanceof StreamableToolInterface) {
                        $name = $instance->getName();
                        $choices[] = "{$name} ({$toolClass})";
                        $validTools[] = $toolClass;
                    }
                }
            } catch (\Throwable) {
                // Skip tools that can't be loaded
            }
        }

        if (empty($choices)) {
            $this->io->warning('No valid MCP tools found.');

            return null;
        }

        $selectedIndex = array_search(
            $this->io->choice('Select a tool to test', $choices),
            $choices
        );

        return $validTools[$selectedIndex] ?? null;
    }

    /**
     * Prompts the user to input JSON data for an object, validates the JSON, and optionally checks for a specific type.
     * Provides error handling for invalid JSON and supports required and optional inputs.
     *
     * @param  array  $input  The input data array, initially empty or pre-filled.
     * @param  mixed  $type  The expected type of the JSON input; used for validation.
     * @param  bool  $required  Indicates whether the input is mandatory. If true and no input is provided, an empty array is returned.
     * @return array|null Returns the decoded JSON as an associative array, or null if input is invalid or not required and skipped.
     */
    private function askForJsonInput(array $input, mixed $type, bool $required): ?array
    {
        $this->io->text('Enter JSON for object (or leave empty to skip):');
        $jsonInput = $this->io->ask('JSON');
        if (! empty($jsonInput)) {
            try {
                $input = json_decode($jsonInput, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TestMcpToolCommandException(json_last_error_msg());
                }
                if ($type === 'array'
                    && ! is_array($input)) {
                    throw new TestMcpToolCommandException('Not an array');
                }
            } catch (\Throwable $e) {
                $this->io->error("Invalid JSON: {$e->getMessage()}");
                $input = null;
            }
        } elseif ($required) {
            $this->io->warning('Required field skipped. Using empty object.');
            $input = [];
        }

        return $input;
    }

    private function askForNumericInput(mixed $default, string $type, bool $required): int|float|null
    {
        $value = $this->io->ask('Value'.($default !== '' ? " (default: {$default})" : ''));
        if ($value === '' && $default !== '') {
            $input = $default;
        } elseif (! is_numeric($value) && $required) {
            $this->io->warning('Required field skipped. Using 0.');
            $input = 0;
        } elseif (is_numeric($value)) {
            $input = ($type === 'integer') ? (int) $value : (float) $value;
        }

        return $input ?? null;
    }

    private function askForStandardInput(mixed $default, bool $required)
    {
        $value = $this->io->ask('Value'.($default !== '' ? " (default: {$default})" : ''));
        if ($value === '' && $default !== '') {
            $input = $default;
        } elseif ($value === '' && $required) {
            $this->io->warning('Required field skipped. Using empty string.');
            $input = '';
        } elseif ($value !== '') {
            $input = $value;
        }

        return $input ?? null;
    }
}
