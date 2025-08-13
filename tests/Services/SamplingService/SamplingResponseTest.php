<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\SamplingService\SamplingResponse;
use PHPUnit\Framework\TestCase;

class SamplingResponseTest extends TestCase
{
    public function test_create_sampling_response(): void
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

    public function test_create_sampling_response_with_minimal_data(): void
    {
        $content = new SamplingContent('text', 'Minimal response');
        $response = new SamplingResponse('assistant', $content);

        $this->assertSame('assistant', $response->getRole());
        $this->assertSame($content, $response->getContent());
        $this->assertNull($response->getModel());
        $this->assertNull($response->getStopReason());
    }

    public function test_to_array_with_all_fields(): void
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

    public function test_to_array_with_minimal_fields(): void
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

    public function test_from_array_with_all_fields(): void
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

    public function test_from_array_with_minimal_fields(): void
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

    public function test_from_array_with_image_content(): void
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

    public function test_round_trip_conversion(): void
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
