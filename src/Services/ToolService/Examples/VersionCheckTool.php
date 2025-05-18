<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ToolService\ToolInterface;
use stdClass;
use Symfony\Component\HttpKernel\Kernel;

final class VersionCheckTool implements ToolInterface
{
    public function getName(): string
    {
        return 'check-version';
    }

    public function getDescription(): string
    {
        return 'Check the current Symfony version.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new stdClass,
            'required' => [],
        ];
    }

    public function getAnnotations(): array
    {
        return [];
    }

    public function execute(array $arguments): string
    {
        $now = (new \DateTime('now'))->format('Y-m-d H:i:s');
        $version = Kernel::VERSION;

        return "current Version: {$version} - {$now}";
    }
}
