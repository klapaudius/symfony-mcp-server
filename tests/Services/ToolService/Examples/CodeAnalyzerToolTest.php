<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
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
        $this->tool = new CodeAnalyzerTool();
        $this->samplingClient = $this->createMock(SamplingClient::class);
    }

    public function testGetName(): void
    {
        $this->assertSame('code_analyzer', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertSame('Analyzes code and provides insights using LLM assistance', $this->tool->getDescription());
    }

    public function testGetInputSchema(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('code', $schema['properties']);
        $this->assertArrayHasKey('analysis_type', $schema['properties']);
        $this->assertContains('code', $schema['required']);
    }

    public function testExecuteWithoutSamplingClient(): void
    {
        $result = $this->tool->execute(['code' => 'echo "Hello";']);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertStringContainsString('requires LLM sampling capability', $sanitized['text']);
    }

    public function testExecuteWithSamplingClientButCannotSample(): void
    {
        $this->samplingClient->method('canSample')->willReturn(false);
        $this->tool->setSamplingClient($this->samplingClient);

        $result = $this->tool->execute(['code' => 'echo "Hello";']);

        $this->assertInstanceOf(TextToolResult::class, $result);
        $sanitized = $result->getSanitizedResult();
        $this->assertStringContainsString('requires LLM sampling capability', $sanitized['text']);
    }

    public function testExecuteWithSuccessfulSampling(): void
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

    public function testExecuteWithDifferentAnalysisTypes(): void
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

    public function testExecuteHandlesSamplingException(): void
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
}
