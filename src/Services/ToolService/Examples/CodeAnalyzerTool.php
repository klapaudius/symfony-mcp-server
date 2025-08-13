<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\SamplingAwareToolInterface;

/**
 * Example tool that demonstrates how to use sampling to analyze code
 */
class CodeAnalyzerTool implements SamplingAwareToolInterface
{
    private ?ProgressNotifierInterface $progressNotifier = null;

    private ?SamplingClient $samplingClient = null;

    public function getName(): string
    {
        return 'code-analyzer';
    }

    public function getDescription(): string
    {
        return 'Analyzes code and provides insights using LLM assistance';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => [
                    'type' => 'string',
                    'description' => 'The code to analyze',
                ],
                'analysis_type' => [
                    'type' => 'string',
                    'enum' => ['security', 'performance', 'readability', 'general'],
                    'description' => 'Type of analysis to perform',
                    'default' => 'general',
                ],
            ],
            'required' => ['code'],
        ];
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $code = $arguments['code'];
        $analysisType = $arguments['analysis_type'] ?? 'general';

        if ($this->samplingClient === null || ! $this->samplingClient->canSample()) {
            return new TextToolResult('Code analysis requires LLM sampling capability');
        }

        // Prepare the prompt based on analysis type
        $prompt = $this->buildAnalysisPrompt($code, $analysisType);

        try {
            // Use sampling to analyze the code
            $response = $this->samplingClient->createTextRequest(
                $prompt,
                new ModelPreferences(
                    hints: [['name' => 'claude-3-sonnet']],
                    intelligencePriority: 0.8
                ),
                null,
                2000
            );

            return new TextToolResult($response->getContent()->getText() ?? 'No analysis provided');
        } catch (\Exception $e) {
            return new TextToolResult('Code analysis failed: '.$e->getMessage());
        }
    }

    private function buildAnalysisPrompt(string $code, string $analysisType): string
    {
        $prompts = [
            'security' => "Analyze this code for security vulnerabilities:\n\n```\n$code\n```\n\nProvide specific security issues and recommendations.",
            'performance' => "Analyze this code for performance issues:\n\n```\n$code\n```\n\nIdentify bottlenecks and suggest optimizations.",
            'readability' => "Analyze this code for readability and maintainability:\n\n```\n$code\n```\n\nSuggest improvements for clarity and structure.",
            'general' => "Provide a comprehensive analysis of this code:\n\n```\n$code\n```\n\nInclude observations about functionality, potential issues, and improvements.",
        ];

        return $prompts[$analysisType] ?? $prompts['general'];
    }

    public function isStreaming(): bool
    {
        return false;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        $this->progressNotifier = $progressNotifier;
    }

    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        );
    }
}
