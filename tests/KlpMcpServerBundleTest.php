<?php

namespace KLP\KlpMcpServer\Tests;

use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ResourcesDefinitionCompilerPass;
use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ToolsDefinitionCompilerPass;
use KLP\KlpMcpServer\KlpMcpServerBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This class tests the build method of the KlpMcpServerBundle class.
 */
class KlpMcpServerBundleTest extends TestCase
{
    /**
     * Tests that the build method adds the ToolsDefinitionCompilerPass to the container.
     */
    public function test_build_adds_compiler_pass()
    {
        $bundle = new KlpMcpServerBundle;
        $containerBuilder = $this->createMock(ContainerBuilder::class);

        $invocations = [
            ToolsDefinitionCompilerPass::class,
            ResourcesDefinitionCompilerPass::class,
        ];
        $containerBuilder
            ->expects($matcher = $this->exactly(2))
            ->method('addCompilerPass')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertInstanceOf($invocations[$matcher->numberOfInvocations() - 1], $argument);

                return true;
            }));

        $bundle->build($containerBuilder);
    }
}
