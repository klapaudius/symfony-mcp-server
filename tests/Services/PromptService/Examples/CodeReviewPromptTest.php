<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\PromptService\Examples;

use KLP\KlpMcpServer\Services\PromptService\Examples\CodeReviewPrompt;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\SamplingResponse;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class CodeReviewPromptTest extends TestCase
{
    private CodeReviewPrompt $prompt;

    protected function setUp(): void
    {
        $this->prompt = new CodeReviewPrompt();
    }

    public function test_get_name(): void
    {
        $this->assertEquals('code-review', $this->prompt->getName());
    }

    public function test_get_description(): void
    {
        $this->assertEquals(
            'Generates a comprehensive code review prompt with context-specific questions',
            $this->prompt->getDescription()
        );
    }

    public function test_get_arguments(): void
    {
        $arguments = $this->prompt->getArguments();
        
        $this->assertCount(3, $arguments);
        
        $this->assertEquals('code', $arguments[0]['name']);
        $this->assertEquals('The code to review', $arguments[0]['description']);
        $this->assertTrue($arguments[0]['required']);
        
        $this->assertEquals('language', $arguments[1]['name']);
        $this->assertEquals('The programming language of the code', $arguments[1]['description']);
        $this->assertFalse($arguments[1]['required']);
        
        $this->assertEquals('focus_areas', $arguments[2]['name']);
        $this->assertStringContainsString('security,performance,style', $arguments[2]['description']);
        $this->assertFalse($arguments[2]['required']);
    }

    public function test_get_messages_without_sampling(): void
    {
        $arguments = [
            'code' => 'function add($a, $b) { return $a + $b; }',
            'language' => 'PHP',
            'focus_areas' => 'security,performance',
        ];

        $messages = $this->prompt->getMessages($arguments);
        $sanitizedMessages = $messages->getSanitizedMessages();

        $this->assertCount(2, $sanitizedMessages);
        
        // Check system message
        $this->assertEquals(PromptMessageInterface::ROLE_ASSISTANT, $sanitizedMessages[0]['role']);
        $this->assertStringContainsString('expert PHP code reviewer', $sanitizedMessages[0]['content']['text']);
        $this->assertStringContainsString('security,performance', $sanitizedMessages[0]['content']['text']);
        
        // Check user message
        $this->assertEquals(PromptMessageInterface::ROLE_USER, $sanitizedMessages[1]['role']);
        $this->assertStringContainsString('function add($a, $b)', $sanitizedMessages[1]['content']['text']);
        $this->assertStringContainsString('Security vulnerabilities', $sanitizedMessages[1]['content']['text']);
        $this->assertStringContainsString('Performance bottlenecks', $sanitizedMessages[1]['content']['text']);
    }

    public function test_get_messages_with_sampling(): void
    {
        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->once())
            ->method('canSample')
            ->willReturn(true);

        $mockResponse = $this->createMock(SamplingResponse::class);
        $mockContent = $this->createMock(SamplingContent::class);
        $mockContent->expects($this->once())
            ->method('getText')
            ->willReturn("- Is there input validation for the parameters?\n- What happens with non-numeric inputs?");
        
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn($mockContent);

        $mockSamplingClient->expects($this->once())
            ->method('createTextRequest')
            ->with(
                $this->stringContains('Analyze this PHP code'),
                $this->isInstanceOf(ModelPreferences::class),
                null,
                500
            )
            ->willReturn($mockResponse);

        $this->prompt->setSamplingClient($mockSamplingClient);

        $arguments = [
            'code' => 'function add($a, $b) { return $a + $b; }',
            'language' => 'PHP',
        ];

        $messages = $this->prompt->getMessages($arguments);
        $sanitizedMessages = $messages->getSanitizedMessages();

        $this->assertCount(3, $sanitizedMessages);
        
        // Check that dynamic questions were added
        $this->assertEquals(PromptMessageInterface::ROLE_USER, $sanitizedMessages[1]['role']);
        $this->assertStringContainsString('Additionally, please address these specific questions', $sanitizedMessages[1]['content']['text']);
        $this->assertStringContainsString('input validation', $sanitizedMessages[1]['content']['text']);
    }

    public function test_get_messages_with_sampling_failure(): void
    {
        $mockSamplingClient = $this->createMock(SamplingClient::class);
        $mockSamplingClient->expects($this->once())
            ->method('canSample')
            ->willReturn(true);

        $mockSamplingClient->expects($this->once())
            ->method('createTextRequest')
            ->willThrowException(new \Exception('Sampling failed'));

        $this->prompt->setSamplingClient($mockSamplingClient);

        $arguments = [
            'code' => 'function add($a, $b) { return $a + $b; }',
        ];

        // Should not throw exception, just skip dynamic questions
        $messages = $this->prompt->getMessages($arguments);
        $sanitizedMessages = $messages->getSanitizedMessages();

        $this->assertCount(2, $sanitizedMessages);
    }

    public function test_get_messages_with_various_focus_areas(): void
    {
        $arguments = [
            'code' => 'class Test {}',
            'focus_areas' => 'style,testing,architecture,unknown',
        ];

        $messages = $this->prompt->getMessages($arguments);
        $sanitizedMessages = $messages->getSanitizedMessages();

        $userMessage = $sanitizedMessages[1]['content']['text'];
        
        $this->assertStringContainsString('Code style, naming conventions', $userMessage);
        $this->assertStringContainsString('Test coverage and testability', $userMessage);
        $this->assertStringContainsString('Architectural patterns', $userMessage);
        $this->assertStringContainsString('General code quality', $userMessage);
    }
}