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
    /**
     * Tests that the process method correctly registers tools as services.
     *
     * @return void
     */
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
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(false, false);

        $container->expects($matcher2 = $this->exactly(count($invocations)))
            ->method('setDefinition')
            ->with($this->callback(function (...$args) use ($invocations, $matcher2) {
                $this->assertEquals($args[0], $invocations[$matcher2->numberOfInvocations() - 1]);
                $this->assertInstanceOf(Definition::class, $args[1]);

                return true;
            }));

        $compilerPass = new ToolsDefinitionCompilerPass;
        $compilerPass->process($container);
    }

    /**
     * Tests that the process method does not register tools that are already defined as services.
     *
     * @return void
     */
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

        $compilerPass = new ToolsDefinitionCompilerPass;
        $compilerPass->process($container);
    }

    /**
     * Tests that the process method registers the tool repository to the server.
     *
     * @return void
     */
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
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls($serverDefinition, $toolRepositoryDefinition);

        $invocations = [
            ['registerToolRepository', [$toolRepositoryDefinition], false],
            ['registerSamplingResponseHandler', [], false]
        ];
        $serverDefinition->expects($matcher = $this->exactly(count($invocations)))
            ->method('addMethodCall')
            ->with($this->callback(function(...$args) use ($invocations, $matcher) {
                $this->assertEquals($args, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }));

        $compilerPass = new ToolsDefinitionCompilerPass;
        $compilerPass->process($container);
    }
}
