<?php

namespace KLP\KlpMcpServer\Tests\DependencyInjection\CompilerPass;

use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ToolsDefinitionCompilerPass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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

        // No providers
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('klp_mcp_server.tool_provider')
            ->willReturn([]);

        $serverDefinition = $this->createMock(Definition::class);
        $toolRepositoryDefinition = $this->createMock(Definition::class);

        // Updated order: tool_repository is retrieved first (for provider registration), then server
        $invocations = [
            'klp_mcp_server.tool_repository',
            'klp_mcp_server.server',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getDefinition')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls($toolRepositoryDefinition, $serverDefinition);

        $invocations = [
            ['registerToolRepository', [$toolRepositoryDefinition], false],
            ['registerSamplingResponseHandler', [], false],
        ];
        $serverDefinition->expects($matcher = $this->exactly(count($invocations)))
            ->method('addMethodCall')
            ->with($this->callback(function (...$args) use ($invocations, $matcher) {
                $this->assertEquals($args, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));

        $compilerPass = new ToolsDefinitionCompilerPass;
        $compilerPass->process($container);
    }

    /**
     * Tests that the process method discovers and registers tool providers.
     *
     * @return void
     */
    public function test_process_discovers_and_registers_tool_providers()
    {
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn([]);

        // Mock findTaggedServiceIds to return two provider services
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('klp_mcp_server.tool_provider')
            ->willReturn([
                'app.tool_provider.first' => [['tag' => 'klp_mcp_server.tool_provider']],
                'app.tool_provider.second' => [['tag' => 'klp_mcp_server.tool_provider']],
            ]);

        $toolRepositoryDefinition = $this->createMock(Definition::class);
        $serverDefinition = $this->createMock(Definition::class);

        $invocations = [
            'klp_mcp_server.tool_repository',
            'klp_mcp_server.server',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getDefinition')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls($toolRepositoryDefinition, $serverDefinition);

        // Expect registerProvider to be called twice for the two providers
        $toolRepositoryDefinition->expects($this->exactly(2))
            ->method('addMethodCall')
            ->with('registerProvider', $this->callback(function ($args) {
                $this->assertCount(1, $args);
                $this->assertInstanceOf(Reference::class, $args[0]);

                return true;
            }));

        $compilerPass = new ToolsDefinitionCompilerPass;
        $compilerPass->process($container);
    }

    /**
     * Tests that tool providers work alongside YAML-configured tools.
     *
     * @return void
     */
    public function test_process_works_with_both_yaml_tools_and_providers()
    {
        eval('namespace App\\Tool; class YamlConfiguredTool {}');
        $container = $this->createMock(ContainerBuilder::class);
        $tools = ['App\\Tool\\YamlConfiguredTool'];

        // Should process YAML tools
        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn($tools);

        $container->expects($this->once())
            ->method('has')
            ->with('App\\Tool\\YamlConfiguredTool')
            ->willReturn(false);

        $container->expects($this->once())
            ->method('setDefinition')
            ->with('App\\Tool\\YamlConfiguredTool', $this->isInstanceOf(Definition::class));

        // Should also discover providers
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('klp_mcp_server.tool_provider')
            ->willReturn([
                'app.tool_provider.custom' => [['tag' => 'klp_mcp_server.tool_provider']],
            ]);

        $toolRepositoryDefinition = $this->createMock(Definition::class);
        $serverDefinition = $this->createMock(Definition::class);

        $invocations = [
            'klp_mcp_server.tool_repository',
            'klp_mcp_server.server',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getDefinition')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls($toolRepositoryDefinition, $serverDefinition);

        // Should register provider with ToolRepository
        $toolRepositoryDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('registerProvider', $this->isType('array'));

        $compilerPass = new ToolsDefinitionCompilerPass;
        $compilerPass->process($container);
    }

    /**
     * Tests that the process method handles no tool providers gracefully.
     *
     * @return void
     */
    public function test_process_handles_no_tool_providers_gracefully()
    {
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn([]);

        // No providers tagged
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('klp_mcp_server.tool_provider')
            ->willReturn([]);

        $toolRepositoryDefinition = $this->createMock(Definition::class);
        $serverDefinition = $this->createMock(Definition::class);

        $invocations = [
            'klp_mcp_server.tool_repository',
            'klp_mcp_server.server',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getDefinition')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls($toolRepositoryDefinition, $serverDefinition);

        // Should not call registerProvider at all
        $toolRepositoryDefinition->expects($this->never())
            ->method('addMethodCall')
            ->with('registerProvider', $this->anything());

        $compilerPass = new ToolsDefinitionCompilerPass;
        $compilerPass->process($container);
    }
}
