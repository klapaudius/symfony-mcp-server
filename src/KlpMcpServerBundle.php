<?php

namespace KLP\KlpMcpServer;

use KLP\KlpMcpServer\Command\MakeMcpToolCommand;
use KLP\KlpMcpServer\Command\TestMcpToolCommand;
use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ToolsDefinitionCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class KlpMcpServerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ToolsDefinitionCompilerPass);
    }
}
