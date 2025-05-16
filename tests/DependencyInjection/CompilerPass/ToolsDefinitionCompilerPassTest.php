<?php

namespace KLP\KlpMcpServer\Tests\DependencyInjection\CompilerPass;

use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ToolsDefinitionCompilerPass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[Small]
class ToolsDefinitionCompilerPassTest extends TestCase
{
    public function test_process_registers_tools_as_services()
    {
        eval('namespace App\\Tool; class SomeTool {}');
        eval('namespace App\\Tool; class AnotherTool {}');
        $container = $this->createMock(ContainerBuilder::class);
        $tools = ['App\\Tool\\SomeTool', 'App\\Tool\\AnotherTool'];

        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn($tools);

        $invocations = [
            'App\\Tool\\SomeTool',
            'App\\Tool\\AnotherTool',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('has')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations()-1]);
                return true;
            }))
            ->willReturnOnConsecutiveCalls(false, false);

        $container->expects($matcher2 = $this->exactly(count($invocations)))
            ->method('setDefinition')
            ->with($this->callback(function (...$args) use ($invocations, $matcher2) {
                $this->assertEquals($args[0], $invocations[$matcher2->numberOfInvocations()-1]);
                $this->assertInstanceOf(Definition::class, $args[1]);
                return true;
            }));

        $compilerPass = new ToolsDefinitionCompilerPass();
        $compilerPass->process($container);
    }

    public function test_process_does_not_register_existing_services()
    {
        $container = $this->createMock(ContainerBuilder::class);
        $tools = ['App\\Tool\\ExistingTool'];

        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn($tools);

        $container->expects($this->once())
            ->method('has')
            ->with('App\\Tool\\ExistingTool')
            ->willReturn(true);

        $container->expects($this->never())
            ->method('setDefinition');

        $compilerPass = new ToolsDefinitionCompilerPass();
        $compilerPass->process($container);
    }

    public function test_process_registers_tool_repository_to_server()
    {
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn([]);

        $serverDefinition = $this->createMock(Definition::class);
        $toolRepositoryDefinition = $this->createMock(Definition::class);

        $invocations = [
            'klp_mcp_server.server',
            'klp_mcp_server.tool_repository',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getDefinition')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations()-1]);
                return true;
            }))
            ->willReturnOnConsecutiveCalls($serverDefinition, $toolRepositoryDefinition);

        $serverDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('registerToolRepository', [$toolRepositoryDefinition]);

        $compilerPass = new ToolsDefinitionCompilerPass();
        $compilerPass->process($container);
    }
}
