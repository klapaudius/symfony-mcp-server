<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\SamplingRequest;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingMessage;
use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use PHPUnit\Framework\TestCase;

class SamplingRequestTest extends TestCase
{
    public function testCreateSamplingRequest(): void
    {
        $message = new SamplingMessage(
            'user',
            new SamplingContent('text', 'Hello, world!')
        );

        $modelPreferences = new ModelPreferences(
            hints: [['name' => 'claude-3-sonnet']],
            costPriority: 0.5,
            speedPriority: 0.3,
            intelligencePriority: 0.8
        );

        $request = new SamplingRequest(
            [$message],
            $modelPreferences,
            'You are a helpful assistant',
            1000
        );

        $this->assertCount(1, $request->getMessages());
        $this->assertSame($modelPreferences, $request->getModelPreferences());
        $this->assertSame('You are a helpful assistant', $request->getSystemPrompt());
        $this->assertSame(1000, $request->getMaxTokens());
    }

    public function testToArray(): void
    {
        $message = new SamplingMessage(
            'user',
            new SamplingContent('text', 'Test message')
        );

        $request = new SamplingRequest([$message]);
        $array = $request->toArray();

        $this->assertArrayHasKey('messages', $array);
        $this->assertCount(1, $array['messages']);
        $this->assertSame('user', $array['messages'][0]['role']);
        $this->assertSame('text', $array['messages'][0]['content']['type']);
        $this->assertSame('Test message', $array['messages'][0]['content']['text']);
    }

    public function testFromArray(): void
    {
        $data = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Hello from array',
                    ],
                ],
            ],
            'modelPreferences' => [
                'hints' => [['name' => 'claude-3']],
                'costPriority' => 0.7,
            ],
            'systemPrompt' => 'System prompt',
            'maxTokens' => 500,
        ];

        $request = SamplingRequest::fromArray($data);

        $this->assertCount(1, $request->getMessages());
        $this->assertSame('user', $request->getMessages()[0]->getRole());
        $this->assertNotNull($request->getModelPreferences());
        $this->assertSame(0.7, $request->getModelPreferences()->getCostPriority());
        $this->assertSame('System prompt', $request->getSystemPrompt());
        $this->assertSame(500, $request->getMaxTokens());
    }

    public function testCreateSamplingRequestWithMinimalData(): void
    {
        $message = new SamplingMessage(
            'assistant',
            new SamplingContent('text', 'Response text')
        );

        $request = new SamplingRequest([$message]);

        $this->assertCount(1, $request->getMessages());
        $this->assertSame($message, $request->getMessages()[0]);
        $this->assertNull($request->getModelPreferences());
        $this->assertNull($request->getSystemPrompt());
        $this->assertNull($request->getMaxTokens());
    }

    public function testToArrayWithFullData(): void
    {
        $messages = [
            new SamplingMessage('system', new SamplingContent('text', 'System message')),
            new SamplingMessage('user', new SamplingContent('text', 'User message')),
        ];

        $modelPreferences = new ModelPreferences(
            [['name' => 'model1'], ['name' => 'model2']],
            0.1,
            0.2,
            0.3
        );

        $request = new SamplingRequest(
            $messages,
            $modelPreferences,
            'Full system prompt',
            2000
        );

        $array = $request->toArray();

        $this->assertArrayHasKey('messages', $array);
        $this->assertArrayHasKey('modelPreferences', $array);
        $this->assertArrayHasKey('systemPrompt', $array);
        $this->assertArrayHasKey('maxTokens', $array);

        $this->assertCount(2, $array['messages']);
        $this->assertSame('system', $array['messages'][0]['role']);
        $this->assertSame('user', $array['messages'][1]['role']);
        $this->assertSame('Full system prompt', $array['systemPrompt']);
        $this->assertSame(2000, $array['maxTokens']);
        $this->assertIsArray($array['modelPreferences']);
        $this->assertSame(0.1, $array['modelPreferences']['costPriority']);
    }

    public function testToArrayWithMinimalData(): void
    {
        $message = new SamplingMessage(
            'user',
            new SamplingContent('text', 'Minimal')
        );

        $request = new SamplingRequest([$message]);
        $array = $request->toArray();

        $this->assertArrayHasKey('messages', $array);
        $this->assertArrayNotHasKey('modelPreferences', $array);
        $this->assertArrayNotHasKey('systemPrompt', $array);
        $this->assertArrayNotHasKey('maxTokens', $array);

        $this->assertCount(1, $array);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Minimal response',
                    ],
                ],
            ],
        ];

        $request = SamplingRequest::fromArray($data);

        $this->assertCount(1, $request->getMessages());
        $this->assertSame('assistant', $request->getMessages()[0]->getRole());
        $this->assertNull($request->getModelPreferences());
        $this->assertNull($request->getSystemPrompt());
        $this->assertNull($request->getMaxTokens());
    }

    public function testRoundTripConversion(): void
    {
        $originalData = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Question?',
                    ],
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Answer!',
                    ],
                ],
            ],
            'modelPreferences' => [
                'hints' => [['name' => 'preferred-model']],
                'speedPriority' => 0.9,
            ],
            'systemPrompt' => 'Be concise',
            'maxTokens' => 100,
        ];

        $request = SamplingRequest::fromArray($originalData);
        $convertedData = $request->toArray();

        $this->assertSame($originalData['messages'], $convertedData['messages']);
        $this->assertSame($originalData['systemPrompt'], $convertedData['systemPrompt']);
        $this->assertSame($originalData['maxTokens'], $convertedData['maxTokens']);
        $this->assertSame($originalData['modelPreferences']['hints'], $convertedData['modelPreferences']['hints']);
        $this->assertSame($originalData['modelPreferences']['speedPriority'], $convertedData['modelPreferences']['speedPriority']);
    }

    public function testWithMultipleMessageTypes(): void
    {
        $messages = [
            new SamplingMessage(
                'user',
                new SamplingContent('text', 'Can you analyze this image?')
            ),
            new SamplingMessage(
                'user',
                new SamplingContent('image', null, ['base64' => 'imagedata'], 'image/png')
            ),
            new SamplingMessage(
                'assistant',
                new SamplingContent('text', 'I can see the image.')
            ),
        ];

        $request = new SamplingRequest($messages);

        $this->assertCount(3, $request->getMessages());
        
        $array = $request->toArray();
        $this->assertCount(3, $array['messages']);
        $this->assertSame('text', $array['messages'][0]['content']['type']);
        $this->assertSame('image', $array['messages'][1]['content']['type']);
        $this->assertSame('text', $array['messages'][2]['content']['type']);
    }

    public function testEmptyMessagesArray(): void
    {
        $request = new SamplingRequest([]);

        $this->assertCount(0, $request->getMessages());
        
        $array = $request->toArray();
        $this->assertArrayHasKey('messages', $array);
        $this->assertCount(0, $array['messages']);
    }

    public function testMaxTokensEdgeCases(): void
    {
        // Test with zero tokens
        $request1 = new SamplingRequest(
            [new SamplingMessage('user', new SamplingContent('text', 'Hi'))],
            null,
            null,
            0
        );
        $this->assertSame(0, $request1->getMaxTokens());

        // Test with large number
        $request2 = new SamplingRequest(
            [new SamplingMessage('user', new SamplingContent('text', 'Hi'))],
            null,
            null,
            1000000
        );
        $this->assertSame(1000000, $request2->getMaxTokens());
    }
}
