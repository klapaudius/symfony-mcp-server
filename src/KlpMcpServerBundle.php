<?php

namespace KLP\KlpMcpServer;

use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ConditionalRoutePass;
use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ResourcesDefinitionCompilerPass;
use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ToolsDefinitionCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class KlpMcpServerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ToolsDefinitionCompilerPass);
        $container->addCompilerPass(new ResourcesDefinitionCompilerPass);
        $container->addCompilerPass(new ConditionalRoutePass);
    }
}
