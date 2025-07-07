<?php

namespace KLP\KlpMcpServer\Tests\Command;

use KLP\KlpMcpServer\Command\MigrateToolSchemaCommand;
use KLP\KlpMcpServer\Services\ToolService\BaseToolInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use ReflectionClass;

#[Small]
class MigrateToolSchemaCommandTest extends TestCase
{
    private MigrateToolSchemaCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->command = new MigrateToolSchemaCommand();
        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);
        
        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/mcp_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }

    public function test_command_configuration(): void
    {
        $this->assertEquals('mcp:migrate-tool-schema', $this->command->getName());
        $this->assertEquals('Migrates a tool class from array-based schema to StructuredSchema', $this->command->getDescription());
        
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('class'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('backup'));
    }

    public function test_execute_with_nonexistent_class(): void
    {
        $this->commandTester->execute([
            'class' => 'NonExistentClass'
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Class "NonExistentClass" not found', $this->commandTester->getDisplay());
    }

    public function test_execute_with_class_without_get_input_schema_method(): void
    {
        // Create a test class without getInputSchema method
        $className = $this->createTestClass('TestClassWithoutMethod', '
            class TestClassWithoutMethod {
                public function someOtherMethod() {
                    return [];
                }
            }
        ');

        $this->commandTester->execute([
            'class' => $className
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('getInputSchema method not found in class', $this->commandTester->getDisplay());
    }

    public function test_execute_with_class_that_does_not_return_array(): void
    {
        // Create a test class that returns something other than array
        $className = $this->createTestClass('TestClassReturnsString', '
            class TestClassReturnsString {
                public function getInputSchema(): string {
                    return "not an array";
                }
            }
        ');

        $this->commandTester->execute([
            'class' => $className
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('getInputSchema does not return an array', $this->commandTester->getDisplay());
    }

    public function test_execute_dry_run_with_valid_class(): void
    {
        // Create a test class with proper getInputSchema method
        $className = $this->createTestClass('TestToolClass', '
            class TestToolClass {
                public function getInputSchema(): array {
                    return [
                        "type" => "object",
                        "properties" => [
                            "name" => [
                                "type" => "string",
                                "description" => "User name"
                            ],
                            "age" => [
                                "type" => "integer", 
                                "description" => "User age"
                            ]
                        ],
                        "required" => ["name"]
                    ];
                }
            }
        ');

        $this->commandTester->execute([
            'class' => $className,
            '--dry-run' => true
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        
        $this->assertStringContainsString('Migrating tool schema for', $output);
        $this->assertStringContainsString('Generated Code', $output);
        $this->assertStringContainsString('StructuredSchema', $output);
        $this->assertStringContainsString('SchemaProperty', $output);
        $this->assertStringContainsString('PropertyType::STRING', $output);
        $this->assertStringContainsString('PropertyType::INTEGER', $output);
        $this->assertStringContainsString('Dry run completed', $output);
    }

    public function test_generate_schema_property_with_all_parameters(): void
    {
        $command = new MigrateToolSchemaCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('generateSchemaProperty');
        $method->setAccessible(true);

        $config = [
            'type' => 'string',
            'description' => 'Test description',
            'default' => 'defaultValue'
        ];

        $result = $method->invoke($command, 'testProperty', $config, true);

        $this->assertStringContainsString("name: 'testProperty'", $result);
        $this->assertStringContainsString('PropertyType::STRING', $result);
        $this->assertStringContainsString("description: 'Test description'", $result);
        $this->assertStringContainsString("default: 'defaultValue'", $result);
        $this->assertStringContainsString('required: true', $result);
    }

    public function test_generate_schema_property_minimal(): void
    {
        $command = new MigrateToolSchemaCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('generateSchemaProperty');
        $method->setAccessible(true);

        $config = ['type' => 'integer'];
        $result = $method->invoke($command, 'simpleProperty', $config, false);

        $this->assertStringContainsString("name: 'simpleProperty'", $result);
        $this->assertStringContainsString('PropertyType::INTEGER', $result);
        $this->assertStringContainsString('required: false', $result);
        $this->assertStringNotContainsString('description:', $result);
        $this->assertStringNotContainsString('default:', $result);
    }

    public function test_map_json_schema_type_to_property_type(): void
    {
        $command = new MigrateToolSchemaCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('mapJsonSchemaTypeToPropertyType');
        $method->setAccessible(true);

        $this->assertEquals('STRING', $method->invoke($command, 'string'));
        $this->assertEquals('INTEGER', $method->invoke($command, 'integer'));
        $this->assertEquals('STRING', $method->invoke($command, 'unknown')); // Fallback
        $this->assertEquals('STRING', $method->invoke($command, 'boolean')); // Fallback
    }

    public function test_generate_use_statements_with_existing_statements(): void
    {
        $command = new MigrateToolSchemaCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('generateUseStatements');
        $method->setAccessible(true);

        $contentWithExisting = '<?php
namespace Test;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use SomeOtherClass;
class TestClass {}';

        $result = $method->invoke($command, $contentWithExisting);
        
        // Should not add StructuredSchema again, but should add the others
        $this->assertStringNotContainsString('use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;', $result);
        $this->assertStringContainsString('use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;', $result);
        $this->assertStringContainsString('use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;', $result);
    }

    public function test_generate_use_statements_with_no_existing_statements(): void
    {
        $command = new MigrateToolSchemaCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('generateUseStatements');
        $method->setAccessible(true);

        $contentWithoutUse = '<?php
namespace Test;
class TestClass {}';

        $result = $method->invoke($command, $contentWithoutUse);
        
        $this->assertStringContainsString('use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;', $result);
        $this->assertStringContainsString('use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;', $result);
        $this->assertStringContainsString('use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;', $result);
    }

    public function test_generate_structured_schema_method(): void
    {
        $command = new MigrateToolSchemaCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('generateStructuredSchemaMethod');
        $method->setAccessible(true);

        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'User name'
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => 'User age'
                ]
            ],
            'required' => ['name']
        ];

        $result = $method->invoke($command, $schema);

        $this->assertStringContainsString('public function getInputSchema(): StructuredSchema', $result);
        $this->assertStringContainsString('return new StructuredSchema(', $result);
        $this->assertStringContainsString("name: 'name'", $result);
        $this->assertStringContainsString("name: 'age'", $result);
        $this->assertStringContainsString('PropertyType::STRING', $result);
        $this->assertStringContainsString('PropertyType::INTEGER', $result);
        $this->assertStringContainsString('required: true', $result);
        $this->assertStringContainsString('required: false', $result);
    }

    public function test_generate_structured_schema_method_with_empty_schema(): void
    {
        $command = new MigrateToolSchemaCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('generateStructuredSchemaMethod');
        $method->setAccessible(true);

        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];

        $result = $method->invoke($command, $schema);

        $this->assertStringContainsString('public function getInputSchema(): StructuredSchema', $result);
        $this->assertStringContainsString('return new StructuredSchema(', $result);
        $this->assertStringContainsString(');', $result);
    }

    private function createTestClass(string $className, string $classDefinition): string
    {
        $fullClassName = 'TestNamespace\\' . $className;
        $content = '<?php
namespace TestNamespace;
' . $classDefinition;

        $filename = $this->tempDir . '/' . $className . '.php';
        file_put_contents($filename, $content);
        
        // Include the file so the class is available
        include_once $filename;
        
        return $fullClassName;
    }

    public function test_command_with_return_type_warning(): void
    {
        // Create a test class with non-array return type
        $className = $this->createTestClass('TestClassWithIntReturn', '
            class TestClassWithIntReturn {
                public function getInputSchema(): int {
                    return 42;
                }
            }
        ');

        $this->commandTester->execute([
            'class' => $className,
            '--dry-run' => true
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('getInputSchema does not return an array', $output);
    }
}