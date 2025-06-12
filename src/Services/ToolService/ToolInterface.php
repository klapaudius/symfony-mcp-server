<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;

trigger_deprecation(
    "klapaudius/klp-mcp-server",
    "1.2.0",
    sprintf(
        'Interface "%s" is deprecated, use "%s" instead.',
        ToolInterface::class,
        StreamableToolInterface::class
    )
);

/**
 * @deprecated The ToolInterface is deprecated. Use StreamableToolInterface instead.
 */
interface ToolInterface extends BaseToolInterface {}
