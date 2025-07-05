<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\PromptService;

use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

interface SamplingAwarePromptInterface extends PromptInterface
{
    /**
     * Sets the sampling client for prompts that need to make sampling requests.
     *
     * This method allows the prompt to receive a sampling client instance that can be used
     * to request LLM assistance during prompt message generation. The sampling client enables the prompt
     * to create nested LLM calls for complex reasoning or content generation tasks.
     *
     * @param  SamplingClient  $samplingClient  The sampling client instance to use for
     *                                          creating sampling requests to the LLM.
     */
    public function setSamplingClient(SamplingClient $samplingClient): void;
}
