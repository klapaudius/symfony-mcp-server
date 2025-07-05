<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ResourceService;

use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

interface SamplingAwareResourceInterface extends ResourceInterface
{
    /**
     * Sets the sampling client for resources that need to make sampling requests.
     *
     * This method allows the resource to receive a sampling client instance that can be used
     * to request LLM assistance during resource retrieval. The sampling client enables the resource
     * to create nested LLM calls for complex reasoning or content generation tasks.
     *
     * @param  SamplingClient  $samplingClient  The sampling client instance to use for
     *                                          creating sampling requests to the LLM.
     */
    public function setSamplingClient(SamplingClient $samplingClient): void;
}
