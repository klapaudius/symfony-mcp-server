<?php

namespace KLP\KlpMcpServer\Tests\Command;

use KLP\KlpMcpServer\Command\MakeMcpToolCommand;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

#[Small]
class MakeMcpToolCommandTest extends TestCase
{
    private static $configContent = <<<YAML
klp_mcp_server:
    tools:
        - KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool
YAML;

    private Kernel|MockObject $kernel;

    private Filesystem|MockObject $filesystem;

    private MakeMcpToolCommand $command;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(Kernel::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->command = new MakeMcpToolCommand($this->kernel, $this->filesystem);
    }

    /**
     * Tests that a tool is successfully created when all conditions are met
     */
    public function test_execute_successfully_creates_tool(): void
    {
        $toolName = '1TestTool';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';
        $toolPath = '/fake-path/src/MCP/Tools/'.$toolName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $toolPath,
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
            $toolPath,
            $configPath,
        ];
        $this->filesystem
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('dumpFile')
            ->with($this->callback(function ($path) use ($invocations, $matcher) {
                $this->assertEquals($path, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));

        $this->filesystem
            ->expects($this->exactly(2))
            ->method('readFile')
            ->willReturn(self::$configContent);

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']); // Simulates pressing "yes" for confirmation
        $commandTester->execute(['name' => $toolName]);

        $this->assertStringContainsString("MCP tool {$toolName} created successfully.", $commandTester->getDisplay());
    }

    /**
     * Tests that execution fails when a tool with the given name already exists
     */
    public function test_execute_fails_when_tool_already_exists(): void
    {
        $toolName = 'ExistingTool';
        $toolPath = '/fake-path/src/MCP/Tools/'.$toolName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->with($toolPath)->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['name' => $toolName]);

        $this->assertStringContainsString("MCP tool {$toolName} already exists!", $commandTester->getDisplay());
    }

    /**
     * Tests that the command prompts for a tool name when none is provided
     */
    public function test_execute_prompts_for_tool_name_if_not_provided(): void
    {
        $toolName = 'GeneratedTool';
        $toolPath = '/fake-path/src/MCP/Tools/'.$toolName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->willReturn(false);

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($toolPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([$toolName, 'yes']);
        $commandTester->execute([]);

        $this->assertStringContainsString("MCP tool {$toolName} created successfully.", $commandTester->getDisplay());
    }

    /**
     * Tests that a tool is not automatically registered when user chooses not to
     */
    public function test_execute_does_not_register_tool_automatically(): void
    {
        $toolName = 'UnregisteredTool';
        $toolPath = '/fake-path/src/MCP/Tools/'.$toolName.'.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->willReturn(false);

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($toolPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);
        $commandTester->execute(['name' => $toolName]);

        $this->assertStringContainsString("Don't forget to register your tool in", $commandTester->getDisplay());
        $this->assertStringContainsString('config/package/klp-mcp-server.yaml:', $commandTester->getDisplay());
    }

    /**
     * Tests that registration fails when configuration file does not exist
     */
    public function test_register_tool_fails_when_config_file_does_not_exist(): void
    {
        $toolName = 'TestToolConfig';
        $toolPath = '/fake-path/src/MCP/Tools/'.$toolName.'Tool.php';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $invocations = [
            $toolPath,
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
            ->with($toolPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['name' => $toolName]);

        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('Config file not found:', $display);
        $this->assertStringContainsString("{$configPath}", $display);
    }

    /**
     * Tests that the YAML indentation detection correctly identifies a 4-space indentation level
     * from the provided YAML content with existing tools defined.
     */
    public function test_detect_yaml_indentation_with_4_space_indentation_and_existing_tools(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
    tools:
        - FirstTool
        - SecondTool
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(8, $result);
    }

    /**
     * Tests that the YAML indentation detection correctly identifies a 4-space indentation level
     * from the provided YAML content with existing tools defined.
     */
    public function test_detect_yaml_indentation_with_2_space_indentation_and_existing_tools(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
  tools:
    - FirstTool
    - SecondTool
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(4, $result);
    }

    /**
     * Tests that detectYamlIndentation returns default indentation when no tools key is found
     */
    public function test_detect_yaml_indentation_with_4_space_indentation_and_no_existing_tools(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
    tools: []
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(8, $result);
    }

    /**
     * Tests that detectYamlIndentation returns default indentation when no tools key is found
     */
    public function test_detect_yaml_indentation_with_2_space_indentation_and_no_existing_tools(): void
    {
        $content = <<<'YAML'
klp_mcp_server:
  tools: []
YAML;

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('detectYamlIndentation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $content);

        $this->assertEquals(4, $result);
    }
}
