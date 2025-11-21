<?php

namespace KLP\KlpMcpServer\Services\ToolService;

// @codeCoverageIgnoreStart
trigger_deprecation(
    'klapaudius/klp-mcp-server',
    '1.2.0',
    sprintf(
        'Interface "%s" is deprecated, use "%s" instead.',
        ToolInterface::class,
        StreamableToolInterface::class
    )
);
// @codeCoverageIgnoreEnd

/**
 * @deprecated The ToolInterface is deprecated. Use StreamableToolInterface instead.
 */
interface ToolInterface extends BaseToolInterface {}
