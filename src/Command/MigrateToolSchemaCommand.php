<?php

namespace KLP\KlpMcpServer\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mcp:migrate-tool-schema',
    description: 'Migrates a tool class from array-based schema to StructuredSchema',
)]
class MigrateToolSchemaCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('class', InputArgument::REQUIRED, 'The fully qualified class name of the tool to migrate')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without modifying files')
            ->addOption('backup', null, InputOption::VALUE_NONE, 'Create a backup of the original file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $className = $input->getArgument('class');
            $dryRun = $input->getOption('dry-run');
            $backup = $input->getOption('backup');

            // Validate class exists
            if (!class_exists($className)) {
                throw new Exception(sprintf('Class "%s" not found', $className));
            }

            $reflection = new \ReflectionClass($className);
            $filePath = $reflection->getFileName();

            if (!$filePath) {
                throw new Exception('Could not determine file path for class');
            }

            $io->title(sprintf('Migrating tool schema for %s', $className));
            $io->text(sprintf('File: %s', $filePath));

            // Read the file content
            $content = file_get_contents($filePath);

            // Check if getInputSchema method exists and returns array
            try {
                $method = $reflection->getMethod('getInputSchema');
                $returnType = $method->getReturnType();

                if ($returnType?->__toString() !== 'array') {
                    $io->warning('getInputSchema method does not return array type. Migration may not be needed.');
                }
            } catch (\ReflectionException $e) {
                throw new Exception('getInputSchema method not found in class');
            }

            // Extract the current schema
            $instance = $reflection->newInstance();
            $currentSchema = $instance->getInputSchema();

            if (!is_array($currentSchema)) {
                throw new Exception('getInputSchema does not return an array. Already migrated?');
            }

            // Generate new method code
            $newMethodCode = $this->generateStructuredSchemaMethod($currentSchema);

            // Generate use statements
            $useStatements = $this->generateUseStatements($content);

            // Show preview
            $io->section('Generated Code');
            $io->text($useStatements);
            $io->text($newMethodCode);

            if ($dryRun) {
                $io->success('Dry run completed. No files were modified.');
                return Command::SUCCESS;
            }

            // Create backup if requested
            if ($backup) {
                $backupPath = $filePath . '.bak';
                copy($filePath, $backupPath);
                $io->text(sprintf('Backup created: %s', $backupPath));
            }

            // Perform the migration
            $migratedContent = $this->migrateContent($content, $useStatements, $newMethodCode);

            file_put_contents($filePath, $migratedContent);

            $io->success('Tool schema migration completed!');
            $io->text('Remember to:');
            $io->listing([
                'Review the generated code for accuracy',
                'Add any missing constraints (min/max values, etc.)',
                'Run your tests to ensure everything works correctly',
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function generateStructuredSchemaMethod(array $schema): string
    {
        $code = "    public function getInputSchema(): StructuredSchema\n";
        $code .= "    {\n";
        $code .= "        return new StructuredSchema(\n";

        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        $propertyLines = [];
        foreach ($properties as $name => $config) {
            $propertyLines[] = $this->generateSchemaProperty($name, $config, in_array($name, $required));
        }

        $code .= implode(",\n", $propertyLines);
        $code .= "\n        );\n";
        $code .= "    }";

        return $code;
    }

    private function generateSchemaProperty(string $name, array $config, bool $required): string
    {
        $type = $this->mapJsonSchemaTypeToPropertyType($config['type'] ?? 'string');
        $description = $config['description'] ?? '';
        $default = $config['default'] ?? '';

        $code = "            new SchemaProperty(\n";
        $code .= sprintf("                name: '%s',\n", $name);
        $code .= sprintf("                type: PropertyType::%s,\n", $type);

        if ($description) {
            $code .= sprintf("                description: '%s',\n", addslashes($description));
        }

        if ($default !== '') {
            $code .= sprintf("                default: '%s',\n", $default);
        }

        $code .= sprintf("                required: %s\n", $required ? 'true' : 'false');
        $code .= "            )";

        return $code;
    }

    private function mapJsonSchemaTypeToPropertyType(string $jsonType): string
    {
        return match ($jsonType) {
            'string' => 'STRING',
            'integer' => 'INTEGER',
            default => 'STRING', // Fallback for unsupported types
        };
    }

    private function generateUseStatements(string $content): string
    {
        $statements = [];

        // Check if these use statements already exist
        if (!str_contains($content, 'use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;')) {
            $statements[] = 'use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;';
        }
        if (!str_contains($content, 'use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;')) {
            $statements[] = 'use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;';
        }
        if (!str_contains($content, 'use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;')) {
            $statements[] = 'use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;';
        }

        return implode("\n", $statements);
    }

    private function migrateContent(string $content, string $useStatements, string $newMethod): string
    {
        // Add use statements after namespace
        if ($useStatements) {
            $pattern = '/(namespace [^;]+;)/';
            $replacement = "$1\n\n" . $useStatements;
            $content = preg_replace($pattern, $replacement, $content, 1);
        }

        // Replace the getInputSchema method
        $pattern = '/public function getInputSchema\(\):\s*array\s*\{[^}]*\}(\s*\})?/s';
        $content = preg_replace_callback($pattern, function($matches) use ($newMethod) {
            // Check if we matched a nested closing brace
            $extraBrace = isset($matches[1]) ? $matches[1] : '';
            return $newMethod . $extraBrace;
        }, $content);

        // Update the return type hint in interface implementations
        $content = str_replace(
            'public function getInputSchema(): array',
            'public function getInputSchema(): StructuredSchema',
            $content
        );

        return $content;
    }
}
