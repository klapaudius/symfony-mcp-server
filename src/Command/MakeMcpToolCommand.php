<?php

namespace KLP\KlpMcpServer\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'make:mcp-tool', description: 'Create a new MCP tool class')]
class MakeMcpToolCommand extends Command
{
    protected const TOOLS_DIRECTORY = 'src/MCP/Tools';

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
                "The class name of the tool to create.\n".
                "If not provided, you will be prompted to enter the class name.\n".
                "The class name should be in StudlyCase and end with 'Tool'. For example: 'MyTool'."
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
            $this->io->error("MCP tool {$className} already exists!");

            return Command::FAILURE;
        }

        // Generate the file using stub
        $this->files->dumpFile($path, $this->buildClass($className));

        $this->io->info("MCP tool {$className} created successfully.");

        $fullClassName = "\\App\\MCP\\Tools\\{$className}";

        // Ask if they want to automatically register the tool
        if ($this->io->confirm('Would you like to automatically register this tool in config/package/klp-mcp-server.yaml?', true)) {
            $this->registerToolInConfig($fullClassName);
        } else {
            $this->io->info("Don't forget to register your tool in config/package/klp-mcp-server.yaml:");
            $this->io->comment('    // config/package/klp-mcp-server.yaml');
            $this->io->comment('    tools;');
            $this->io->comment('        // other tools...');
            $this->io->comment("        {$fullClassName},");
        }

        // Display testing instructions
        $this->io->newLine();
        $this->io->info('You can now test your tool with the following command:');
        $this->io->comment('    php bin/console mcp:test-tool '.$className);
        $this->io->info('Or view all available tools:');
        $this->io->comment('    php bin/console mcp:test-tool --list');

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
            $name = $this->io->ask('What is the name of the tool?');
        }

        // Clean up the input: remove multiple spaces, hyphens, underscores
        // and handle mixed case input
        $name = preg_replace('/[\s\-_]+/', ' ', trim($name));

        // Convert to StudlyCase
        $name = $this->studly($name ?? '');

        // Ensure the class name ends with "Tool" if not already
        if (! str_ends_with($name, 'Tool')) {
            $name .= 'Tool';
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
        // Create the file in the app/MCP/Tools directory
        return sprintf('%s/%s/%s.php',
            $this->kernel->getProjectDir(),
            self::TOOLS_DIRECTORY,
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

        // Generate a kebab-case tool name without the 'Tool' suffix
        $toolName = $this->kebab(preg_replace('/Tool$/', '', $className));

        // Ensure tool name doesn't have unwanted characters
        $toolName = preg_replace('/[^a-z0-9\-]/', '', $toolName);

        // Ensure no consecutive hyphens
        $toolName = preg_replace('/\-+/', '-', $toolName);

        // Ensure it starts with a letter
        if (! preg_match('/^[a-z]/', $toolName)) {
            $toolName = 'tool-'.$toolName;
        }

        return $this->replaceStubPlaceholders($stub, $className, $toolName);
    }

    /**
     * Get the stub file path.
     *
     * @return string
     */
    protected function getStubPath()
    {
        return __DIR__.'/../stubs/tool.stub';
    }

    /**
     * Replace the stub placeholders with actual values.
     *
     * @return string
     */
    protected function replaceStubPlaceholders(string $stub, string $className, string $toolName)
    {
        return str_replace(
            ['{{ className }}', '{{ namespace }}', '{{ toolName }}'],
            [$className, 'App\\MCP\\Tools', $toolName],
            $stub
        );
    }

    /**
     * Register the tool in the MCP server configuration file.
     *
     * @param  string  $toolClassName  Fully qualified class name of the tool
     * @return bool Whether registration was successful
     */
    /**
     * Detect indentation level for YAML tools entries.
     *
     * This function analyzes the existing YAML structure to determine how many
     * spaces are used for indentation in the tools array.
     */
    protected function detectYamlIndentation(string $content): int
    {
        $indentation = null;

        // Look for the first tool entry to determine indentation
        if (preg_match('/tools:.*\n(\s+)-\s/s', $content, $matches)) {
            if (isset($matches[1])) {
                // Count the number of spaces before the first tool entry
                $indentation = strlen($matches[1]);
            }
        }
        if (! $indentation && preg_match('/([[:blank:]]+)tools:.*\[\]/s', $content, $matches)) {
            if (isset($matches[1])) {
                $indentation = strlen($matches[1])*2;
            }
        }

        return $indentation ?? 8;
    }

    protected function registerToolInConfig(string $toolClassName): bool
    {
        $configPath = $this->kernel->getProjectDir().'/config/packages/klp_mcp_server.yaml';

        if (! $this->files->exists($configPath)) {
            $this->io->error("Config file not found: {$configPath}");

            return false;
        }

        $content = $this->files->readFile($configPath);

        // Find the tools array in the config file
        if (! preg_match('/(tools:\s*(\s*-\s*[[:alnum:]\\\\]*|\[\]))/s', $content, $matches)) {
            $this->io->error('Could not locate tools array in config file.');

            return false;
        }

        $toolsArrayContent = $matches[1];

        // Detect the indentation level used in the YAML file
        $indentation = $this->detectYamlIndentation($content);
        $indentStr = str_repeat(' ', $indentation);
        $fullEntry = "\n{$indentStr}- {$toolClassName}";

        // Check if the tool is already registered
        if (str_contains($toolsArrayContent, $toolClassName)) {
            $this->io->info('Tool is already registered in config file.');

            return true;
        }

        // Add the new tool to the tools array
        $newToolsArrayContent = $toolsArrayContent.$fullEntry;
        $newContent = str_replace($toolsArrayContent, $newToolsArrayContent, $content);
        $newContent = str_replace('tools: []', 'tools:', $newContent);

        // Write the updated content back to the config file
        try {
            $this->files->dumpFile($configPath, $newContent);
            $this->io->info('Tool registered successfully in config/packages/klp_mcp_server.yml');

            return true;
        } catch (\Throwable) {
            $this->io->error('Failed to update config file. Please manually register the tool.');

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
        return strtolower(preg_replace('/(?<!^)([A-Z])(?=[a-z])/', '-$0', lcfirst($preg_replace)));
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
