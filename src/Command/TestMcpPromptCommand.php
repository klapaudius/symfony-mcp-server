<?php

namespace KLP\KlpMcpServer\Command;

use KLP\KlpMcpServer\Exceptions\TestMcpPromptCommandException;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mcp:test-prompt', description: 'Test an MCP prompt with simulated arguments')]
class TestMcpPromptCommand extends Command
{
    private const NOT_SPECIFIED = 'Not specified';

    private const VALID_ROLES = [
        PromptMessageInterface::ROLE_USER,
        PromptMessageInterface::ROLE_ASSISTANT,
    ];

    public function __construct(private readonly PromptRepository $promptRepository)
    {
        parent::__construct();
    }

    private InputInterface $input;

    private SymfonyStyle $io;

    public function displayResult(PromptInterface $prompt, array $arguments, array $messages): void
    {
        $this->io->success('Prompt executed successfully!');

        // Display the arguments used
        if (! empty($arguments)) {
            $this->io->section('Arguments Used');
            $this->io->text(json_encode($arguments, JSON_PRETTY_PRINT));
        }

        // Display the generated messages
        $this->io->section('Generated Messages');
        $this->io->text(sprintf('Total messages: %d', count($messages)));

        foreach ($messages as $index => $message) {
            $this->io->newLine();

            // Validate and display role
            $role = $message['role'] ?? self::NOT_SPECIFIED;
            if (! in_array($role, self::VALID_ROLES, true)) {
                $this->io->error(sprintf('Invalid role "%s" detected. Valid roles are: %s', $role, implode(', ', self::VALID_ROLES)));
            }

            $this->io->text([
                sprintf('Message #%d:', $index + 1),
                sprintf('Role: %s', $role),
            ]);

            // Display content based on structure
            if (isset($message['content'])) {
                $content = $message['content'];
                $this->io->text(sprintf('Type: %s', $content['type'] ?? self::NOT_SPECIFIED));

                if ($content['type'] === 'text' && isset($content['text'])) {
                    $this->io->text([
                        'Content:',
                        $content['text'],
                    ]);
                } elseif ($content['type'] === 'image' && isset($content['data'])) {
                    $this->io->text([
                        'Image Data: '.$content['data'],
                        'MIME Type: '.($content['mimeType'] ?? self::NOT_SPECIFIED),
                    ]);
                } elseif ($content['type'] === 'resource' && isset($content['resource'])) {
                    $this->io->text([
                        'Resource URI: '.$content['resource']['uri'],
                        'Text: '.($content['resource']['text'] ?? ''),
                        'MIME Type: '.($content['resource']['mimeType'] ?? self::NOT_SPECIFIED),
                    ]);
                }
            } else {
                // Fallback to display the raw message structure
                $this->io->text([
                    'Raw message:',
                    json_encode($message, JSON_PRETTY_PRINT),
                ]);
            }
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('prompt', InputArgument::OPTIONAL, 'The name of the prompt to test')
            ->addOption('input', '-i', InputOption::VALUE_OPTIONAL, 'JSON input for the prompt')
            ->addOption('list', '-l', InputOption::VALUE_NONE, 'List all available prompts')
            ->setDescription('Test an MCP prompt with simulated inputs')
            ->setHelp(<<<'EOT'
mcp:test-prompt {prompt? : The name of the prompt to test} {--inputs= : JSON inputs for the prompt} {--list : List all available prompts}
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

        // List all prompts if --list option is provided
        return $this->input->getOption('list')
            ? $this->listAllPrompts()
            : $this->testPrompt();
    }

    private function testPrompt(): int
    {
        try {
            // Get the prompt instance and display its schema
            $prompt = $this->getPromptInstance();
            $this->displaySchema($prompt);

            // Get arguments
            $arguments = $this->getArgumentsFromOption()
                ?? $this->askForArguments($prompt->getArguments());
            if ($arguments === null) {
                throw new TestMcpPromptCommandException('Invalid inputs.');
            }

            // Execute the prompt
            $this->io->text([
                'Executing prompt with inputs:',
                json_encode($arguments, JSON_PRETTY_PRINT),
            ]);

            try {
                $collectionMessage = $prompt->getMessages($arguments);
                $messages = $collectionMessage->getSanitizedMessages();
                $this->displayResult($prompt, $arguments, $messages);

                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $this->io->error("Error executing prompt: {$e->getMessage()}");
                $this->io->text([
                    'Stack trace:',
                    $e->getTraceAsString(),
                ]);

                return Command::FAILURE;
            }
        } catch (TestMcpPromptCommandException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Resolves and retrieves an instance of a prompt based on the provided identifier
     * from the input or user prompt.
     *
     * @return PromptInterface Returns the prompt instance if found.
     *
     * @throws TestMcpPromptCommandException If no prompt is specified or if the prompt cannot be resolved.
     */
    public function getPromptInstance(): PromptInterface
    {
        // Get the prompt name from the argument or prompt for it
        $identifier = $this->input->getArgument('prompt') ?: $this->askForPrompt();
        if (! $identifier) {
            throw new TestMcpPromptCommandException('No prompt specified.');
        }

        // Try to get the prompt from the repository
        $prompt = $this->promptRepository->getPrompt($identifier);

        if (! $prompt) {
            throw new TestMcpPromptCommandException("Prompt '{$identifier}' not found.");
        }

        return $prompt;
    }

    /**
     * Displays the schema information for a specific prompt.
     *
     * @param  PromptInterface  $prompt  The prompt instance whose schema is to be displayed.
     */
    public function displaySchema(PromptInterface $prompt): void
    {
        $promptClass = get_class($prompt);
        $this->io->text([
            "Testing prompt: {$prompt->getName()} ({$promptClass})",
            "Description: {$prompt->getDescription()}",
        ]);
        $this->io->newLine();

        // Display arguments schema
        $arguments = $prompt->getArguments();
        if (empty($arguments)) {
            $this->io->text('This prompt accepts no arguments.');
        } else {
            $this->io->text('Arguments:');
            foreach ($arguments as $arg) {
                $required = ($arg['required'] ?? false) ? '(required)' : '(optional)';
                $description = $arg['description'] ?? 'No description';
                $this->io->text([
                    "  - {$arg['name']}: {$required}",
                    "    Description: {$description}",
                ]);
            }
        }
    }

    public function getArgumentsFromOption(): ?array
    {
        // If arguments are provided as an option, use that
        $argumentsOption = $this->input->getOption('arguments');
        if ($argumentsOption) {
            try {
                $decodedArguments = json_decode($argumentsOption, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TestMcpPromptCommandException(json_last_error_msg());
                }

                return $decodedArguments;
            } catch (\Throwable $e) {
                $this->io->error("Invalid JSON arguments: {$e->getMessage()}");
            }
        }

        return null;
    }

    /**
     * Get arguments from user.
     */
    public function askForArguments(array $argumentsSchema): ?array
    {
        $arguments = [];

        if (empty($argumentsSchema)) {
            return $arguments;
        }

        foreach ($argumentsSchema as $argSchema) {
            $name = $argSchema['name'];
            $description = $argSchema['description'] ?? '';
            $required = $argSchema['required'] ?? false;

            $this->io->text("Argument: {$name} {$description}");

            $default = '';
            $value = $this->io->ask('Value'.($default !== '' ? " (default: {$default})" : ''));

            if ($value === '' && $required) {
                $this->io->warning('Required field skipped. Using empty string.');
                $arguments[$name] = '';
            } elseif ($value !== '') {
                $arguments[$name] = $value;
            }
        }

        return $arguments;
    }

    /**
     * List all available prompts.
     */
    private function listAllPrompts(): int
    {
        $prompts = $this->promptRepository->getPrompts();

        if (empty($prompts)) {
            $this->io->warning('No MCP prompts are configured. Add prompts in config/packages/klp_mcp_server.yaml');

            return Command::SUCCESS;
        }

        $tableData = [];

        foreach ($prompts as $prompt) {
            $tableData[] = [
                'name' => $prompt->getName(),
                'class' => get_class($prompt),
                'description' => substr($prompt->getDescription(), 0, 50),
                'arguments' => count($prompt->getArguments()),
            ];
        }

        $this->io->info('Available MCP Prompts:');
        $this->io->table(['Name', 'Class', 'Description', 'Arguments'], $tableData);

        $this->io->text([
            'To test a specific prompt, run:',
            '    php bin/console mcp:test-prompt [prompt_name]',
            "    php bin/console mcp:test-prompt [prompt_name] --arguments='{\"name\":\"value\"}'",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Ask the user to select a prompt.
     */
    protected function askForPrompt(): ?string
    {
        $prompts = $this->promptRepository->getPrompts();

        if (empty($prompts)) {
            $this->io->warning('No MCP prompts are configured. Add prompts in config/packages/klp_mcp_server.yaml');

            return null;
        }

        $choices = [];
        $promptNames = [];

        foreach ($prompts as $prompt) {
            $name = $prompt->getName();
            $choices[] = "{$name} (".get_class($prompt).')';
            $promptNames[] = $name;
        }

        $selectedIndex = array_search(
            $this->io->choice('Select a prompt to test', $choices),
            $choices
        );

        return $promptNames[$selectedIndex] ?? null;
    }
}
