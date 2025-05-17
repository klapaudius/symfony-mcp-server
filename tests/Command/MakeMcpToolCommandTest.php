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

    public function testExecuteSuccessfullyCreatesTool(): void
    {
        $toolName = '1TestTool';
        $configPath = '/fake-path/config/packages/klp_mcp_server.yaml';
        $toolPath = '/fake-path/src/MCP/Tools/' . $toolName . '.php';

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

    public function testExecuteFailsWhenToolAlreadyExists(): void
    {
        $toolName = 'ExistingTool';
        $toolPath = '/fake-path/src/MCP/Tools/' . $toolName . '.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->with($toolPath)->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['name' => $toolName]);

        $this->assertStringContainsString("MCP tool {$toolName} already exists!", $commandTester->getDisplay());
    }

    public function testExecutePromptsForToolNameIfNotProvided(): void
    {
        $toolName = 'GeneratedTool';
        $toolPath = '/fake-path/src/MCP/Tools/' . $toolName . '.php';

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

    public function testExecuteDoesNotRegisterToolAutomatically(): void
    {
        $toolName = 'UnregisteredTool';
        $toolPath = '/fake-path/src/MCP/Tools/' . $toolName . '.php';

        $this->kernel->method('getProjectDir')->willReturn('/fake-path');
        $this->filesystem->method('exists')->willReturn(false);

        $this->filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with($toolPath, $this->anything());

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);
        $commandTester->execute(['name' => $toolName]);

        $this->assertStringContainsString("Don't forget to register your tool in config/package/klp-mcp-server.yaml:", $commandTester->getDisplay());
    }

    public function testRegisterToolFailsWhenConfigFileDoesNotExist(): void
    {
        $toolName = 'TestToolConfig';
        $toolPath = '/fake-path/src/MCP/Tools/' . $toolName . 'Tool.php';
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

        $this->assertStringContainsString("Config file not found: {$configPath}", $commandTester->getDisplay());
    }
}
