<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

interface SamplingAwareToolInterface extends StreamableToolInterface
{
    /**
     * Sets the sampling client for tools that need to make sampling requests.
     *
     * This method allows the tool to receive a sampling client instance that can be used
     * to request LLM assistance during tool execution. The sampling client enables the tool
     * to create nested LLM calls for complex reasoning or content generation tasks.
     *
     * @param  SamplingClient  $samplingClient  The sampling client instance to use for
     *                                          creating sampling requests to the LLM.
     */
    public function setSamplingClient(SamplingClient $samplingClient): void;
}
