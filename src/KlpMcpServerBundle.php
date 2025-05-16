<?php

namespace KLP\KlpMcpServer;

use KLP\KlpMcpServer\Console\Commands\MakeMcpToolCommand;
use KLP\KlpMcpServer\Console\Commands\TestMcpToolCommand;
use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ToolsDefinitionCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class KlpMcpServerBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();
        $configPath = $this->container->getParameter('kernel.project_dir').'/config/packages/klp_mcp_server.yaml';
        $filesystem = new Filesystem;

        // Check if the file already exists
        if (! $filesystem->exists($configPath)) {
            $defaultConfig = __DIR__.'/Resources/config/packages/klp_mcp_server.yaml';
            $filesystem->copy($defaultConfig, $configPath);
        }
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/symfony-package-tools
         */
        $package
            ->name('symfony-mcp-server')
            ->hasConfigFile('mcp-server')
            ->hasCommands([
                MakeMcpToolCommand::class,
                TestMcpToolCommand::class,
            ]);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ToolsDefinitionCompilerPass());
    }
}
