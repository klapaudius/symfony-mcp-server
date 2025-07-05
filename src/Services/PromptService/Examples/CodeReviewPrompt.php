<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\PromptService\Examples;

use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\SamplingAwarePromptInterface;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

/**
 * Example prompt that uses sampling to generate dynamic code review prompts.
 *
 * This prompt demonstrates how to use the sampling feature to create
 * context-aware prompts based on the code being reviewed.
 */
class CodeReviewPrompt implements SamplingAwarePromptInterface
{
    private ?SamplingClient $samplingClient = null;

    public function getName(): string
    {
        return 'code-review';
    }

    public function getDescription(): string
    {
        return 'Generates a comprehensive code review prompt with context-specific questions';
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'code',
                'description' => 'The code to review',
                'required' => true,
            ],
            [
                'name' => 'language',
                'description' => 'The programming language of the code',
                'required' => false,
            ],
            [
                'name' => 'focus_areas',
                'description' => 'Comma-separated list of areas to focus on (e.g., security,performance,style)',
                'required' => false,
            ],
        ];
    }

    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $code = $arguments['code'] ?? '';
        $language = $arguments['language'] ?? 'unknown';
        $focusAreas = $arguments['focus_areas'] ?? 'general';

        $collection = new CollectionPromptMessage;

        // Add the system message
        $collection->addMessage(
            new TextPromptMessage(
                PromptMessageInterface::ROLE_ASSISTANT,
                $this->getSystemPrompt($language, $focusAreas)
            )
        );

        // If we have sampling capabilities, generate dynamic questions
        if ($this->samplingClient !== null && $this->samplingClient->canSample() && ! empty($code)) {
            $dynamicQuestions = $this->generateDynamicQuestions($code, $language);
            if ($dynamicQuestions !== null) {
                $collection->addMessage(
                    new TextPromptMessage(
                        PromptMessageInterface::ROLE_USER,
                        $dynamicQuestions
                    )
                );
            }
        }

        // Add the main code review request
        $collection->addMessage(
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                $this->getMainReviewPrompt($code, $focusAreas)
            )
        );

        return $collection;
    }

    private function getSystemPrompt(string $language, string $focusAreas): string
    {
        return sprintf(
            'You are an expert %s code reviewer. Your task is to provide a thorough code review focusing on: %s. '.
            'Be specific in your feedback, provide examples when suggesting improvements, and explain the reasoning behind your recommendations.',
            $language !== 'unknown' ? $language : 'software',
            $focusAreas
        );
    }

    private function getMainReviewPrompt(string $code, string $focusAreas): string
    {
        $areas = array_map('trim', explode(',', $focusAreas));
        $areaPrompts = [];

        foreach ($areas as $area) {
            switch (strtolower($area)) {
                case 'security':
                    $areaPrompts[] = '- Security vulnerabilities and potential attack vectors';
                    break;
                case 'performance':
                    $areaPrompts[] = '- Performance bottlenecks and optimization opportunities';
                    break;
                case 'style':
                    $areaPrompts[] = '- Code style, naming conventions, and formatting';
                    break;
                case 'testing':
                    $areaPrompts[] = '- Test coverage and testability of the code';
                    break;
                case 'architecture':
                    $areaPrompts[] = '- Architectural patterns and design decisions';
                    break;
                default:
                    $areaPrompts[] = '- General code quality and best practices';
            }
        }

        return sprintf(
            "Please review the following code:\n\n```\n%s\n```\n\nFocus your review on:\n%s\n\n".
            'Provide specific, actionable feedback with examples where appropriate.',
            $code,
            implode("\n", $areaPrompts)
        );
    }

    private function generateDynamicQuestions(string $code, string $language): ?string
    {
        try {
            // Use sampling to analyze the code and generate specific questions
            $prompt = sprintf(
                'Analyze this %s code and generate 3-5 specific, insightful questions that a code reviewer should ask. '.
                "Focus on potential issues, design decisions, or areas that need clarification:\n\n```\n%s\n```\n\n".
                'Format your response as a bulleted list of questions only.',
                $language,
                $code
            );

            $response = $this->samplingClient->createTextRequest(
                $prompt,
                new ModelPreferences(
                    hints: [['name' => 'claude-3-haiku']],
                    intelligencePriority: 0.5,
                    speedPriority: 0.8
                ),
                null,
                500
            );

            $questions = $response->getContent()->getText();
            if (! empty($questions)) {
                return "Additionally, please address these specific questions:\n\n".$questions;
            }
        } catch (\Exception $e) {
            // If sampling fails, we'll just skip the dynamic questions
            // and use the static prompt
        }

        return null;
    }

    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }
}
