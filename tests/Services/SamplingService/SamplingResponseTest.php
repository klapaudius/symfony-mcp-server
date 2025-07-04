<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\SamplingResponse;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use PHPUnit\Framework\TestCase;

class SamplingResponseTest extends TestCase
{
    public function testCreateSamplingResponse(): void
    {
        $content = new SamplingContent('text', 'Hello, this is a response');
        $response = new SamplingResponse(
            'assistant',
            $content,
            'claude-3-sonnet',
            'stop_sequence'
        );

        $this->assertSame('assistant', $response->getRole());
        $this->assertSame($content, $response->getContent());
        $this->assertSame('claude-3-sonnet', $response->getModel());
        $this->assertSame('stop_sequence', $response->getStopReason());
    }

    public function testCreateSamplingResponseWithMinimalData(): void
    {
        $content = new SamplingContent('text', 'Minimal response');
        $response = new SamplingResponse('assistant', $content);

        $this->assertSame('assistant', $response->getRole());
        $this->assertSame($content, $response->getContent());
        $this->assertNull($response->getModel());
        $this->assertNull($response->getStopReason());
    }

    public function testToArrayWithAllFields(): void
    {
        $content = new SamplingContent('text', 'Full response');
        $response = new SamplingResponse(
            'assistant',
            $content,
            'claude-3-opus',
            'max_tokens'
        );

        $array = $response->toArray();

        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('model', $array);
        $this->assertArrayHasKey('stopReason', $array);
        
        $this->assertSame('assistant', $array['role']);
        $this->assertSame('claude-3-opus', $array['model']);
        $this->assertSame('max_tokens', $array['stopReason']);
        $this->assertIsArray($array['content']);
        $this->assertSame('text', $array['content']['type']);
        $this->assertSame('Full response', $array['content']['text']);
    }

    public function testToArrayWithMinimalFields(): void
    {
        $content = new SamplingContent('text', 'Minimal response');
        $response = new SamplingResponse('user', $content);

        $array = $response->toArray();

        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayNotHasKey('model', $array);
        $this->assertArrayNotHasKey('stopReason', $array);
        
        $this->assertSame('user', $array['role']);
        $this->assertIsArray($array['content']);
    }

    public function testFromArrayWithAllFields(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => [
                'type' => 'text',
                'text' => 'Response from array',
            ],
            'model' => 'claude-3-haiku',
            'stopReason' => 'end_turn',
        ];

        $response = SamplingResponse::fromArray($data);

        $this->assertSame('assistant', $response->getRole());
        $this->assertSame('claude-3-haiku', $response->getModel());
        $this->assertSame('end_turn', $response->getStopReason());
        
        $content = $response->getContent();
        $this->assertSame('text', $content->getType());
        $this->assertSame('Response from array', $content->getText());
    }

    public function testFromArrayWithMinimalFields(): void
    {
        $data = [
            'role' => 'system',
            'content' => [
                'type' => 'text',
                'text' => 'System message',
            ],
        ];

        $response = SamplingResponse::fromArray($data);

        $this->assertSame('system', $response->getRole());
        $this->assertNull($response->getModel());
        $this->assertNull($response->getStopReason());
        
        $content = $response->getContent();
        $this->assertSame('text', $content->getType());
        $this->assertSame('System message', $content->getText());
    }

    public function testFromArrayWithImageContent(): void
    {
        $data = [
            'role' => 'assistant',
            'content' => [
                'type' => 'image',
                'data' => ['base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='],
                'mimeType' => 'image/png',
            ],
        ];

        $response = SamplingResponse::fromArray($data);

        $this->assertSame('assistant', $response->getRole());
        
        $content = $response->getContent();
        $this->assertSame('image', $content->getType());
        $this->assertNull($content->getText());
        $this->assertIsArray($content->getData());
        $this->assertSame('image/png', $content->getMimeType());
    }

    public function testRoundTripConversion(): void
    {
        $originalData = [
            'role' => 'assistant',
            'content' => [
                'type' => 'text',
                'text' => 'Round trip test',
            ],
            'model' => 'claude-3',
            'stopReason' => 'completed',
        ];

        $response = SamplingResponse::fromArray($originalData);
        $convertedData = $response->toArray();

        $this->assertSame($originalData['role'], $convertedData['role']);
        $this->assertSame($originalData['model'], $convertedData['model']);
        $this->assertSame($originalData['stopReason'], $convertedData['stopReason']);
        $this->assertSame($originalData['content']['type'], $convertedData['content']['type']);
        $this->assertSame($originalData['content']['text'], $convertedData['content']['text']);
    }
}