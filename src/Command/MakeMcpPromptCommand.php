<?php

namespace KLP\KlpMcpServer\Command;

use KLP\KlpMcpServer\Exceptions\MakeMcpPromptCommandException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'make:mcp-prompt', description: 'Create a new MCP prompt class')]
class MakeMcpPromptCommand extends Command
{
    protected const PROMPTS_DIRECTORY = 'src/MCP/Prompts';

    private SymfonyStyle $io;

    private InputInterface $input;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(private KernelInterface $kernel, private ?Filesystem $files = null)
    {
        parent::__construct();
        $this->files ??= new Filesystem;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name',
                InputArgument::OPTIONAL,
                "The class name of the prompt to create.\n".
                "If not provided, you will be prompted to enter the class name.\n".
                "The class name should be in StudlyCase and end with 'Prompt'. For example: 'MyPrompt'."
            );
    }

    /**
     * Execute the console command.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->io = new SymfonyStyle($input, $output);
        $className = $this->getClassName();
        $path = $this->getPath($className);

        // Check if file already exists
        if ($this->files->exists($path)) {
            $this->io->error("MCP prompt {$className} already exists!");

            return Command::FAILURE;
        }

        // Generate the file using stub
        $this->files->dumpFile($path, $this->buildClass($className));

        $this->io->info("MCP prompt {$className} created successfully.");

        $fullClassName = "\\App\\MCP\\Prompts\\{$className}";

        // Ask if they want to automatically register the prompt
        if ($this->io->confirm('Would you like to automatically register this prompt in config/package/klp-mcp-server.yaml?', true)) {
            $this->registerPromptInConfig($fullClassName);
        } else {
            $this->io->info("Don't forget to register your prompt in config/package/klp-mcp-server.yaml:");
            $this->io->comment('    // config/package/klp-mcp-server.yaml');
            $this->io->comment('    prompts:');
            $this->io->comment('        // other prompts...');
            $this->io->comment("        {$fullClassName},");
        }

        // Display testing instructions
        $this->io->newLine();
        $this->io->info('You can now test your prompt with the following command:');
        $this->io->comment('    php bin/console mcp:test-prompt '.$className);
        $this->io->info('Or view all available prompts:');
        $this->io->comment('    php bin/console mcp:test-prompt --list');

        return 0;
    }

    /**
     * Get the class name from the command argument.
     *
     * @return string
     */
    protected function getClassName()
    {
        $name = $this->input->getArgument('name');
        if (! $name) {
            $name = $this->io->ask('What is the name of the prompt?');
        }

        // Clean up the input: remove multiple spaces, hyphens, underscores
        // and handle mixed case input
        $name = preg_replace('/[\s\-_]+/', ' ', trim((string) $name));

        // Convert to StudlyCase
        $name = $this->studly($name ?? '');

        // Ensure the class name ends with "Prompt" if not already
        if (! str_ends_with($name, 'Prompt')) {
            $name .= 'Prompt';
        }

        return $name;
    }

    /**
     * Get the destination file path.
     *
     * @return string
     */
    protected function getPath(string $className)
    {
        // Create the file in the app/MCP/Prompts directory
        return sprintf('%s/%s/%s.php',
            $this->kernel->getProjectDir(),
            self::PROMPTS_DIRECTORY,
            $className
        );
    }

    /**
     * Build the class with the given name.
     *
     * @return string
     */
    protected function buildClass(string $className)
    {
        $stub = $this->files->readFile($this->getStubPath());

        // Generate a kebab-case prompt name without the 'Prompt' suffix
        $promptName = $this->kebab(preg_replace('/Prompt$/', '', $className) ?? $className);

        // Ensure prompt name doesn't have unwanted characters
        $promptName = preg_replace('/[^a-z0-9\-]/', '', $promptName) ?? '';

        // Ensure no consecutive hyphens
        $promptName = preg_replace('/\-+/', '-', $promptName) ?? '';

        // Ensure it starts with a letter
        if (! preg_match('/^[a-z]/', $promptName)) {
            $promptName = 'prompt-'.$promptName;
        }

        return $this->replaceStubPlaceholders($stub, $className, $promptName);
    }

    /**
     * Get the stub file path.
     *
     * @return string
     */
    protected function getStubPath()
    {
        return __DIR__.'/../stubs/prompt.stub';
    }

    /**
     * Replace the stub placeholders with actual values.
     *
     * @return string
     */
    protected function replaceStubPlaceholders(string $stub, string $className, string $promptName)
    {
        return str_replace(
            ['{{ className }}', '{{ namespace }}', '{{ toolName }}'],
            [$className, 'App\\MCP\\Prompts', $promptName],
            $stub
        );
    }

    /**
     * Detect indentation level for YAML prompts entries.
     *
     * This function analyzes the existing YAML structure to determine how many
     * spaces are used for indentation in the prompts array.
     */
    protected function detectYamlIndentation(string $content): int
    {
        $indentation = null;

        // Look for the first prompt entry to determine indentation
        if (preg_match('/prompts:.*\n(\s+)-\s/s', $content, $matches)) {
            // Count the number of spaces before the first prompt entry
            $indentation = strlen($matches[1]);
        }
        if (! $indentation
            && preg_match('/([[:blank:]]+)prompts:/s', $content, $matches)) {
            $indentation = strlen($matches[1]) * 2;
        }

        return $indentation ?? 8;
    }

    /**
     * Register the prompt in the MCP server configuration file.
     *
     * @param  string  $promptClassName  Fully qualified class name of the prompt
     * @return bool Whether registration was successful
     */
    protected function registerPromptInConfig(string $promptClassName): bool
    {
        try {
            $configPath = $this->kernel->getProjectDir().'/config/packages/klp_mcp_server.yaml';

            if (! $this->files->exists($configPath)) {
                throw new MakeMcpPromptCommandException("Config file not found: {$configPath}");
            }

            $content = $this->files->readFile($configPath);

            // Find the prompts array in the config file
            if (! preg_match('/(prompts:\s*(\s*-\s*[[:alnum:]\\\\]*|\[\]))/s', $content, $matches)) {
                throw new MakeMcpPromptCommandException('Could not locate prompts array in config file.');
            }

            $promptsArrayContent = $matches[1];

            // Detect the indentation level used in the YAML file
            $indentation = $this->detectYamlIndentation($content);
            $indentStr = str_repeat(' ', $indentation);
            $fullEntry = "\n{$indentStr}- {$promptClassName}";

            // Check if the prompt is already registered
            if (str_contains($promptsArrayContent, $promptClassName)) {
                $this->io->info('Prompt is already registered in config file.');
            } else {
                // Add the new prompt to the prompts array
                $newPromptsArrayContent = $promptsArrayContent.$fullEntry;
                $newContent = str_replace($promptsArrayContent, $newPromptsArrayContent, $content);
                $newContent = str_replace('prompts: []', 'prompts:', $newContent);

                // Write the updated content back to the config file
                $this->files->dumpFile($configPath, $newContent);
                $this->io->info('Prompt registered successfully in config/packages/klp_mcp_server.yml');
            }

            return true;
        } catch (\Throwable $e) {
            $this->io->error("Failed to update config file: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Converts a given string or array to kebab-case format.
     *
     * @param  string  $preg_replace  Input data that can be an array, a string, or null to transform into kebab-case.
     * @return string Returns the transformed string in kebab-case format, or null if input is null.
     */
    private function kebab(string $preg_replace): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])(?=[a-z])/', '-$0', lcfirst($preg_replace)) ?? '');
    }

    /**
     * Converts a given string to StudlyCase format.
     *
     * @param  string  $name  Input string to be transformed into StudlyCase, or null.
     * @return string Returns the transformed string in StudlyCase format, or null if input is null.
     */
    private function studly(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}
