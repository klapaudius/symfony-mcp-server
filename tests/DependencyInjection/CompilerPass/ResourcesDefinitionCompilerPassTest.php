<?php

namespace KLP\KlpMcpServer\Tests\DependencyInjection\CompilerPass;

use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ResourcesDefinitionCompilerPass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[Small]
class ResourcesDefinitionCompilerPassTest extends TestCase
{
    public function testProcessRegistersResourcesAsServices()
    {
        eval('namespace App\Resource; class ResourceA {}');
        eval('namespace App\Resource; class ResourceB {}');

        $container = $this->createMock(ContainerBuilder::class);
        $resources = ['App\Resource\ResourceA', 'App\Resource\ResourceB'];

        $invocations = [
            'klp_mcp_server.resources',
            'klp_mcp_server.resources_templates'
        ];
        $container->expects($matcher = $this->exactly(2))
            ->method('getParameter')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                $resources,
                []
            );

        $invocations = [
            'App\Resource\ResourceA',
            'App\Resource\ResourceB',
        ];
        $container->expects($matcher = $this->exactly(2))
            ->method('has')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }))
            ->willReturnOnConsecutiveCalls(false, false);

        $container->expects($matcher = $this->exactly(2))
            ->method('setDefinition')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }));

        $pass = new ResourcesDefinitionCompilerPass();
        $pass->process($container);
    }

    public function testProcessDoesNotRegisterAlreadyDefinedResources()
    {
        $container = $this->createMock(ContainerBuilder::class);
        $resources = ['App\Resource\ExistingResource'];

        $invocations = [
            'klp_mcp_server.resources',
            'klp_mcp_server.resources_templates'
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getParameter')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                $resources,
                []
            );

        $container->expects($this->once())
            ->method('has')
            ->with('App\Resource\ExistingResource')
            ->willReturn(true);

        $container->expects($this->never())
            ->method('setDefinition');

        $pass = new ResourcesDefinitionCompilerPass();
        $pass->process($container);
    }

    public function testProcessAddsMethodCallToServer()
    {
        $container = $this->createMock(ContainerBuilder::class);
        $serverDefinition = $this->createMock(Definition::class);
        $resourceRepositoryDefinition = $this->createMock(Definition::class);

        $invocations = [
            'klp_mcp_server.resources',
            'klp_mcp_server.resources_templates'
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getParameter')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }))
            ->willReturn([]);

        $invocations = [
            'klp_mcp_server.server',
            'klp_mcp_server.resource_repository',
        ];
        $container->expects($matcher = $this->exactly(2))
            ->method('getDefinition')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);
                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                $serverDefinition,
                $resourceRepositoryDefinition,
            );

        $serverDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('registerResourceRepository', [$resourceRepositoryDefinition]);

        $pass = new ResourcesDefinitionCompilerPass();
        $pass->process($container);
    }
}
