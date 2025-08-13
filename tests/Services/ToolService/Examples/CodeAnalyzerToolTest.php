<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\SamplingResponse;
use KLP\KlpMcpServer\Services\ToolService\Examples\CodeAnalyzerTool;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use PHPUnit\Framework\TestCase;

class CodeAnalyzerToolTest extends TestCase
{
    private CodeAnalyzerTool $tool;

    private SamplingClient $samplingClient;

    protected function setUp(): void
    {
        $this->tool = new CodeAnalyzerTool;
        $this->samplingClient = $this->createMock(SamplingClient::class);
    }

    public function test_get_name(): void
    {
        $this->assertSame('code-analyzer', $this->tool->getName());
    }

    public function test_get_description(): void
    {
        $this->assertSame('Analyzes code and provides insights using LLM assistance', $this->tool->getDescription());
    }

    public function test_get_input_schema(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('code', $schema['properties']);
        $this->assertArrayHasKey('analysis_type', $schema['properties']);
        $this->assertContains('code', $schema['required']);
    }

    public function test_execute_without_sampling_client(): void
    {
        $result = $this->tool->execute(['code' => 'echo "Hello";']);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertStringContainsString('requires LLM sampling capability', $sanitized['text']);
    }

    public function test_execute_with_sampling_client_but_cannot_sample(): void
    {
        $this->samplingClient->method('canSample')->willReturn(false);
        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute(['code' => 'echo "Hello";']);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertStringContainsString('requires LLM sampling capability', $sanitized['text']);
    }

    public function test_execute_with_successful_sampling(): void
    {
        $this->samplingClient->method('canSample')->willReturn(true);

        $analysisText = 'This code prints "Hello" to the output. It is a simple echo statement.';
        $content = new SamplingContent('text', $analysisText);
        $response = new SamplingResponse('assistant', $content);

        $this->samplingClient->expects($this->once())
            ->method('createTextRequest')
            ->with(
                $this->stringContains('echo "Hello"'),
                $this->anything(),
                null,
                2000
            )
            ->willReturn($response);

        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute(['code' => 'echo "Hello";']);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertSame($analysisText, $sanitized['text']);
    }

    public function test_execute_with_different_analysis_types(): void
    {
        $this->samplingClient->method('canSample')->willReturn(true);

        $content = new SamplingContent('text', 'Security analysis result');
        $response = new SamplingResponse('assistant', $content);

        $this->samplingClient->expects($this->once())
            ->method('createTextRequest')
            ->with(
                $this->stringContains('security vulnerabilities'),
                $this->anything(),
                null,
                2000
            )
            ->willReturn($response);

        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute([
            'code' => 'eval($_GET["cmd"]);',
            'analysis_type' => 'security',
        ]);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertSame('Security analysis result', $sanitized['text']);
    }

    public function test_execute_handles_sampling_exception(): void
    {
        $this->samplingClient->method('canSample')->willReturn(true);
        $this->samplingClient->method('createTextRequest')
            ->willThrowException(new \Exception('Sampling service unavailable'));

        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute(['code' => 'echo "Hello";']);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertStringContainsString('Code analysis failed: Sampling service unavailable', $sanitized['text']);
    }

    public function test_is_streaming(): void
    {
        $this->assertFalse($this->tool->isStreaming());
    }

    public function test_set_progress_notifier(): void
    {
        $progressNotifier = $this->createMock(ProgressNotifierInterface::class);

        // This should not throw any exceptions
        $this->tool->setProgressNotifier($progressNotifier);

        // Execute to ensure it works with progress notifier set
        $result = $this->tool->execute(['code' => 'test code']);
        $this->assertInstanceOf(TextToolResult::class, $result);
    }

    public function test_get_annotations(): void
    {
        $annotations = $this->tool->getAnnotations();

        $this->assertTrue($annotations->isReadOnlyHint());
        $this->assertFalse($annotations->isDestructiveHint());
        $this->assertTrue($annotations->isIdempotentHint());
        $this->assertFalse($annotations->isOpenWorldHint());
    }

    public function test_execute_with_all_analysis_types(): void
    {
        $analysisTypes = ['performance', 'readability', 'general'];
        $expectedPrompts = [
            'performance' => 'performance issues',
            'readability' => 'readability and maintainability',
            'general' => 'comprehensive analysis',
        ];

        foreach ($analysisTypes as $type) {
            // Create a fresh tool and sampling client for each test
            $tool = new CodeAnalyzerTool;
            $samplingClient = $this->createMock(SamplingClient::class);

            $samplingClient->method('canSample')->willReturn(true);

            $content = new SamplingContent('text', ucfirst($type).' analysis result');
            $response = new SamplingResponse('assistant', $content);

            $samplingClient->expects($this->once())
                ->method('createTextRequest')
                ->with(
                    $this->stringContains($expectedPrompts[$type]),
                    $this->anything(),
                    null,
                    2000
                )
                ->willReturn($response);

            $tool->setSamplingClient($samplingClient);

            $result = $tool->execute([
                'code' => 'function test() { return true; }',
                'analysis_type' => $type,
            ]);

            $sanitized = $result->getSanitizedResult();
            $this->assertSame(ucfirst($type).' analysis result', $sanitized['text']);
        }
    }

    public function test_execute_with_model_preferences(): void
    {
        $this->samplingClient->method('canSample')->willReturn(true);

        $content = new SamplingContent('text', 'Analysis with model preferences');
        $response = new SamplingResponse('assistant', $content);

        $this->samplingClient->expects($this->once())
            ->method('createTextRequest')
            ->with(
                $this->anything(),
                $this->callback(function ($modelPrefs) {
                    return $modelPrefs instanceof ModelPreferences
                        && $modelPrefs->getHints() === [['name' => 'claude-3-sonnet']]
                        && $modelPrefs->getIntelligencePriority() === 0.8;
                }),
                null,
                2000
            )
            ->willReturn($response);

        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute(['code' => 'test code']);
        $sanitized = $result->getSanitizedResult();
        $this->assertSame('Analysis with model preferences', $sanitized['text']);
    }

    public function test_execute_with_null_response_text(): void
    {
        $this->samplingClient->method('canSample')->willReturn(true);

        // Create response with null text
        $content = new SamplingContent('text', null);
        $response = new SamplingResponse('assistant', $content);

        $this->samplingClient->expects($this->once())
            ->method('createTextRequest')
            ->willReturn($response);

        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute(['code' => 'echo "test";']);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertSame('No analysis provided', $sanitized['text']);
    }

    public function test_get_input_schema_details(): void
    {
        $schema = $this->tool->getInputSchema();

        // Test complete schema structure
        $this->assertSame('object', $schema['type']);

        // Test code property
        $this->assertArrayHasKey('code', $schema['properties']);
        $this->assertSame('string', $schema['properties']['code']['type']);
        $this->assertSame('The code to analyze', $schema['properties']['code']['description']);

        // Test analysis_type property
        $this->assertArrayHasKey('analysis_type', $schema['properties']);
        $this->assertSame('string', $schema['properties']['analysis_type']['type']);
        $this->assertSame(['security', 'performance', 'readability', 'general'], $schema['properties']['analysis_type']['enum']);
        $this->assertSame('Type of analysis to perform', $schema['properties']['analysis_type']['description']);
        $this->assertSame('general', $schema['properties']['analysis_type']['default']);

        // Test required fields
        $this->assertSame(['code'], $schema['required']);
    }

    public function test_execute_with_invalid_analysis_type(): void
    {
        $this->samplingClient->method('canSample')->willReturn(true);

        $content = new SamplingContent('text', 'General analysis fallback');
        $response = new SamplingResponse('assistant', $content);

        // When invalid analysis type is provided, it should fall back to general
        $this->samplingClient->expects($this->once())
            ->method('createTextRequest')
            ->with(
                $this->stringContains('comprehensive analysis'),
                $this->anything(),
                null,
                2000
            )
            ->willReturn($response);

        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute([
            'code' => 'test code',
            'analysis_type' => 'invalid_type',
        ]);

        $sanitized = $result->getSanitizedResult();
        $this->assertSame('General analysis fallback', $sanitized['text']);
    }
}
