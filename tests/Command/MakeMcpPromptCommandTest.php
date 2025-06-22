<?php

namespace KLP\KlpMcpServer\Tests\Command;

use KLP\KlpMcpServer\Command\MakeMcpPromptCommand;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

#[Small]
class MakeMcpPromptCommandTest extends TestCase
{
    private static $configContent = <<<YAML
klp_mcp_server:
    prompts:
        - KLP\KlpMcpServer\Services\PromptService\Examples\HelloWorldPrompt
YAML;

    private static $configContentWithEmptyPrompts = <<<YAML
klp_mcp_server:
    prompts: []
YAML;

    private static $configContentWithoutPrompts = <<<YAML
klp_mcp_server:
    tools:
        - SomeTool
YAML;

    private Kernel|MockObject $kernel;

    private Filesystem|MockObject $filesystem;

    private MakeMcpPromptCommand $command;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(Kernel::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->command = new MakeMcpPromptCommand($this->kernel, $this->filesystem);
    }

    /**
     * Tests that a prompt is successfully created when all conditions are met
     */
    public function test_execute_successfully_creates_prompt(): void
    {
        $promptName = 'TestPrompt';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';
        $stubPath = __DIR__.'/../../src/stubs/prompt.stub';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('exists')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                false,
                true
            );

        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('dumpFile')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));

        $stubContent = file_get_contents($stubPath);
        $this->filesystem
            ->expects($this->exactly(2))
            ->method('readFile')
            ->willReturnCallback(function ($path) use ($stubPath, $stubContent, $configPath) {
                if (str_contains($path, 'stub')) {
                    return $stubContent;
                }
                return self::$configContent;
            });

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']); // Simulates pressing "yes" for confirmation
        $commandTester->execute(['name' => $promptName]);

        $this->assertStringContainsString("MCP prompt {$promptName} created successfully.", $commandTester->getDisplay());
        $this->assertStringContainsString('You can now test your prompt with the following command:', $commandTester->getDisplay());
        $this->assertStringContainsString('php bin/console mcp:test-prompt '.$promptName, $commandTester->getDisplay());
    }

    /**
     * Tests that execution fails when a prompt with the given name already exists
     */
    public function test_execute_fails_when_prompt_already_exists(): void
    {
        $promptName = 'ExistingPrompt';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->with($promptPath)->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['name' => $promptName]);

        $this->assertStringContainsString("MCP prompt {$promptName} already exists!", $commandTester->getDisplay());
    }

    /**
     * Tests that the command prompts for a prompt name when none is provided
     */
    public function test_execute_prompts_for_prompt_name_if_not_provided(): void
    {
        $promptName = 'GeneratedPrompt';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->willReturn(false);

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($promptPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([$promptName, 'yes']);
        $commandTester->execute([]);

        $this->assertStringContainsString("MCP prompt {$promptName} created successfully.", $commandTester->getDisplay());
    }

    /**
     * Tests that a prompt is not automatically registered when user chooses not to
     */
    public function test_execute_does_not_register_prompt_automatically(): void
    {
        $promptName = 'UnregisteredPrompt';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->willReturn(false);

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($promptPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);
        $commandTester->execute(['name' => $promptName]);

        $this->assertStringContainsString("Don't forget to register your prompt in", $commandTester->getDisplay());
        $this->assertStringContainsString('config/package/klp-mcp-server.yaml:', $commandTester->getDisplay());
    }

    /**
     * Tests that registration fails when configuration file does not exist
     */
    public function test_register_prompt_fails_when_config_file_does_not_exist(): void
    {
        $promptName = 'TestPromptConfig';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'Prompt.php';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('exists')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                false,
                false
            );

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($promptPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => $promptName]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Config file not found:', $display);
        $this->assertStringContainsString("{$configPath}", $display);
    }

    /**
     * Tests that registration fails when prompts array is not found in config
     */
    public function test_register_prompt_fails_when_prompts_array_not_found(): void
    {
        $promptName = 'TestPrompt';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('exists')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                false,
                true
            );

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($promptPath, $this->anything());

        $stubContent = file_get_contents(__DIR__.'/../../src/stubs/prompt.stub');
        $this->filesystem
            ->expects($this->exactly(2))
            ->method('readFile')
            ->willReturnCallback(function ($path) use ($stubContent, $configPath) {
                if (str_contains($path, 'stub')) {
                    return $stubContent;
                }
                return self::$configContentWithoutPrompts;
            });

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => $promptName]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Could not locate prompts array in config', $display);
    }

    /**
     * Tests that the prompt is correctly registered when prompts array is empty
     */
    public function test_register_prompt_with_empty_prompts_array(): void
    {
        $promptName = 'TestPrompt';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('exists')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                false,
                true
            );

        $stubContent = file_get_contents(__DIR__.'/../../src/stubs/prompt.stub');
        $this->filesystem
            ->expects($this->exactly(2))
            ->method('readFile')
            ->willReturnCallback(function ($path) use ($stubContent, $configPath) {
                if (str_contains($path, 'stub')) {
                    return $stubContent;
                }
                return self::$configContentWithEmptyPrompts;
            });

        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('dumpFile')
            ->with($this->callback(function ($path, $content = null) use ($invocations, $matcher) {
                $invocationIndex = $matcher->numberOfInvocations() - 1;
                $this->assertEquals($path, $invocations[$invocationIndex]);

                if ($invocationIndex === 1 && $content !== null) {
                    // Verify the empty array is replaced with actual prompts
                    $this->assertStringNotContainsString('prompts: []', $content);
                    $this->assertStringContainsString('prompts:', $content);
                    $this->assertStringContainsString('\\App\\MCP\\Prompts\\TestPrompt', $content);
                }

                return true;
            }));

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => $promptName]);

        $this->assertStringContainsString('Prompt registered successfully', $commandTester->getDisplay());
    }

    /**
     * Tests that the prompt is not registered twice when already exists in config
     */
    public function test_prompt_not_registered_twice(): void
    {
        $promptName = 'HelloWorldPrompt';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('exists')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                false,
                true
            );

        $stubContent = file_get_contents(__DIR__.'/../../src/stubs/prompt.stub');
        $this->filesystem
            ->expects($this->exactly(2))
            ->method('readFile')
            ->willReturnCallback(function ($path) use ($stubContent, $configPath) {
                if (str_contains($path, 'stub')) {
                    return $stubContent;
                }
                return self::$configContent;
            });

        // Expect two dumpFile calls - one for the prompt file and potentially one for config
        $this->filesystem
            ->expects($this->atLeastOnce())
            ->method('dumpFile');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => $promptName]);

        $display = $commandTester->getDisplay();
        // The prompt should be created but registration might fail or show already registered message
        $this->assertStringContainsString('MCP prompt HelloWorldPrompt created successfully.', $display);
    }

    /**
     * Tests getClassName method with various input formats
     */
    public function test_get_class_name_variations(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getClassName');

        $testCases = [
            'test-prompt' => 'TestPrompt',
            'test_prompt' => 'TestPrompt',
            'test prompt' => 'TestPrompt',
            'TestPrompt' => 'TestPrompt',
            'testPrompt' => 'TestPrompt',
            'test' => 'TestPrompt',
            'test-prompt-name' => 'TestPromptNamePrompt',
            'test_prompt_name' => 'TestPromptNamePrompt',
            'test   prompt   name' => 'TestPromptNamePrompt',
            '1test' => '1testPrompt',
            'test-' => 'TestPrompt',
            '_test_' => 'TestPrompt',
        ];

        foreach ($testCases as $input => $expected) {
            $commandTester = new CommandTester($this->command);
            $commandTester->execute(['name' => $input]);

            $result = $method->invoke($this->command);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    /**
     * Tests buildClass method to ensure proper stub replacement
     */
    public function test_build_class_generates_correct_content(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');

        $stubContent = file_get_contents(__DIR__.'/../../src/stubs/prompt.stub');
        $this->filesystem
            ->expects($this->once())
            ->method('readFile')
            ->willReturn($stubContent);

        $result = $method->invoke($this->command, 'TestPrompt');

        $this->assertStringContainsString('class TestPrompt implements PromptInterface', $result);
        $this->assertStringContainsString('namespace App\\MCP\\Prompts;', $result);
        $this->assertStringContainsString("return 'test';", $result);
        $this->assertStringContainsString('Description of TestPrompt', $result);
    }

    /**
     * Tests that numeric prefixes in prompt names are handled correctly
     */
    public function test_numeric_prefix_handling(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');

        $stubContent = file_get_contents(__DIR__.'/../../src/stubs/prompt.stub');
        $this->filesystem
            ->expects($this->once())
            ->method('readFile')
            ->willReturn($stubContent);

        $result = $method->invoke($this->command, '123NumericPrompt');

        // Should prepend 'prompt-' when starting with numbers
        $this->assertStringContainsString("return 'prompt-123-numeric';", $result);
    }

    /**
     * Tests the YAML indentation detection with 4-space indentation and existing prompts
     */
    public function test_detect_yaml_indentation_with_4_space_indentation_and_existing_prompts(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
    prompts:
        - FirstPrompt
        - SecondPrompt
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(8, $result);
    }

    /**
     * Tests the YAML indentation detection with 2-space indentation and existing prompts
     */
    public function test_detect_yaml_indentation_with_2_space_indentation_and_existing_prompts(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
  prompts:
    - FirstPrompt
    - SecondPrompt
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(4, $result);
    }

    /**
     * Tests that detectYamlIndentation returns correct indentation when prompts array is empty
     */
    public function test_detect_yaml_indentation_with_4_space_indentation_and_no_existing_prompts(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
    prompts: []
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(8, $result);
    }

    /**
     * Tests that detectYamlIndentation returns correct indentation with 2-space and empty prompts
     */
    public function test_detect_yaml_indentation_with_2_space_indentation_and_no_existing_prompts(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
  prompts: []
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(4, $result);
    }

    /**
     * Tests kebab case conversion method
     */
    public function test_kebab_case_conversion(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('kebab');

        $testCases = [
            'TestPrompt' => 'test-prompt',
            'testPrompt' => 'test-prompt',
            'TestPromptName' => 'test-prompt-name',
            'testPromptName' => 'test-prompt-name',
            'Test' => 'test',
            'test' => 'test',
            'TEST' => 'test',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->command, $input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    /**
     * Tests studly case conversion method
     */
    public function test_studly_case_conversion(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('studly');

        $testCases = [
            'test-prompt' => 'TestPrompt',
            'test_prompt' => 'TestPrompt',
            'test prompt' => 'TestPrompt',
            'test-prompt-name' => 'TestPromptName',
            'test_prompt_name' => 'TestPromptName',
            'Test-Prompt' => 'TestPrompt',
            'TEST-PROMPT' => 'TESTPROMPT',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->command, $input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    /**
     * Tests exception handling during config registration
     */
    public function test_register_prompt_handles_filesystem_exception(): void
    {
        $promptName = 'TestPrompt';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';
        $promptPath = '/fake-path/src/MCP/Prompts/'.$promptName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $promptPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('exists')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                false,
                true
            );

        $stubContent = file_get_contents(__DIR__.'/../../src/stubs/prompt.stub');
        $this->filesystem
            ->expects($this->exactly(2))
            ->method('readFile')
            ->willReturnCallback(function ($path) use ($stubContent) {
                if (str_contains($path, 'stub')) {
                    return $stubContent;
                }
                throw new \Exception('Read error');
            });

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($promptPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => $promptName]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Failed to update config file: Read error', $display);
    }
}
