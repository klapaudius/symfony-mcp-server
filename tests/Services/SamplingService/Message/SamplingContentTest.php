<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService\Message;

use KLP\KlpMcpServer\Services\SamplingService\Message\SamplingContent;
use PHPUnit\Framework\TestCase;

class SamplingContentTest extends TestCase
{
    public function test_create_text_content(): void
    {
        $content = new SamplingContent('text', 'Hello, world!');

        $this->assertSame('text', $content->getType());
        $this->assertSame('Hello, world!', $content->getText());
        $this->assertNull($content->getData());
        $this->assertNull($content->getMimeType());
    }

    public function test_create_image_content(): void
    {
        $imageData = ['base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='];
        $content = new SamplingContent('image', null, $imageData, 'image/png');

        $this->assertSame('image', $content->getType());
        $this->assertNull($content->getText());
        $this->assertSame($imageData, $content->getData());
        $this->assertSame('image/png', $content->getMimeType());
    }

    public function test_create_audio_content(): void
    {
        $audioData = ['base64' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YQAAAAA='];
        $content = new SamplingContent('audio', null, $audioData, 'audio/wav');

        $this->assertSame('audio', $content->getType());
        $this->assertNull($content->getText());
        $this->assertSame($audioData, $content->getData());
        $this->assertSame('audio/wav', $content->getMimeType());
    }

    public function test_create_resource_content(): void
    {
        $resourceData = [
            'uri' => 'file:///home/user/document.pdf',
            'name' => 'document.pdf',
            'description' => 'Important document',
        ];
        $content = new SamplingContent('resource', null, $resourceData, 'application/pdf');

        $this->assertSame('resource', $content->getType());
        $this->assertNull($content->getText());
        $this->assertSame($resourceData, $content->getData());
        $this->assertSame('application/pdf', $content->getMimeType());
    }

    public function test_create_minimal_content(): void
    {
        $content = new SamplingContent('text');

        $this->assertSame('text', $content->getType());
        $this->assertNull($content->getText());
        $this->assertNull($content->getData());
        $this->assertNull($content->getMimeType());
    }

    public function test_to_array_with_text_content(): void
    {
        $content = new SamplingContent('text', 'Sample text');
        $array = $content->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('text', $array);
        $this->assertArrayNotHasKey('data', $array);
        $this->assertArrayNotHasKey('mimeType', $array);

        $this->assertSame('text', $array['type']);
        $this->assertSame('Sample text', $array['text']);
    }

    public function test_to_array_with_image_content(): void
    {
        $imageData = ['base64' => 'abc123', 'width' => 100, 'height' => 100];
        $content = new SamplingContent('image', null, $imageData, 'image/jpeg');
        $array = $content->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayNotHasKey('text', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('mimeType', $array);

        $this->assertSame('image', $array['type']);
        $this->assertSame($imageData, $array['data']);
        $this->assertSame('image/jpeg', $array['mimeType']);
    }

    public function test_to_array_with_minimal_data(): void
    {
        $content = new SamplingContent('custom');
        $array = $content->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayNotHasKey('text', $array);
        $this->assertArrayNotHasKey('data', $array);
        $this->assertArrayNotHasKey('mimeType', $array);

        $this->assertSame(['type' => 'custom'], $array);
    }

    public function test_from_array_with_text_content(): void
    {
        $data = [
            'type' => 'text',
            'text' => 'Hello from array',
        ];

        $content = SamplingContent::fromArray($data);

        $this->assertSame('text', $content->getType());
        $this->assertSame('Hello from array', $content->getText());
        $this->assertNull($content->getData());
        $this->assertNull($content->getMimeType());
    }

    public function test_from_array_with_complex_data(): void
    {
        $complexData = [
            'base64' => 'SGVsbG8gV29ybGQ=',
            'metadata' => [
                'author' => 'Test Author',
                'created' => '2024-01-01',
            ],
        ];

        $data = [
            'type' => 'document',
            'data' => $complexData,
            'mimeType' => 'application/octet-stream',
        ];

        $content = SamplingContent::fromArray($data);

        $this->assertSame('document', $content->getType());
        $this->assertNull($content->getText());
        $this->assertSame($complexData, $content->getData());
        $this->assertSame('application/octet-stream', $content->getMimeType());
    }

    public function test_from_array_with_minimal_data(): void
    {
        $data = ['type' => 'minimal'];

        $content = SamplingContent::fromArray($data);

        $this->assertSame('minimal', $content->getType());
        $this->assertNull($content->getText());
        $this->assertNull($content->getData());
        $this->assertNull($content->getMimeType());
    }

    public function test_round_trip_conversion_text(): void
    {
        $originalData = [
            'type' => 'text',
            'text' => 'Round trip text content',
        ];

        $content = SamplingContent::fromArray($originalData);
        $convertedData = $content->toArray();

        $this->assertSame($originalData, $convertedData);
    }

    public function test_round_trip_conversion_complex(): void
    {
        $originalData = [
            'type' => 'video',
            'data' => [
                'url' => 'https://example.com/video.mp4',
                'duration' => 120,
                'format' => 'mp4',
            ],
            'mimeType' => 'video/mp4',
        ];

        $content = SamplingContent::fromArray($originalData);
        $convertedData = $content->toArray();

        $this->assertSame($originalData, $convertedData);
    }

    public function test_content_variations(): void
    {
        // Test empty text
        $emptyText = new SamplingContent('text', '');
        $this->assertSame('', $emptyText->getText());

        // Test empty array data
        $emptyData = new SamplingContent('data', null, []);
        $this->assertSame([], $emptyData->getData());

        // Test mixed content (text type with data - unusual but valid)
        $mixed = new SamplingContent('text', 'Hello', ['extra' => 'data'], 'text/plain');
        $this->assertSame('text', $mixed->getType());
        $this->assertSame('Hello', $mixed->getText());
        $this->assertSame(['extra' => 'data'], $mixed->getData());
        $this->assertSame('text/plain', $mixed->getMimeType());
    }

    public function test_special_characters_in_content(): void
    {
        $specialText = 'Hello "World" with \'quotes\' and \n newlines \t tabs';
        $content = new SamplingContent('text', $specialText);

        $this->assertSame($specialText, $content->getText());

        // Test serialization preserves special characters
        $array = $content->toArray();
        $this->assertSame($specialText, $array['text']);

        // Test deserialization preserves special characters
        $restored = SamplingContent::fromArray($array);
        $this->assertSame($specialText, $restored->getText());
    }

    public function test_nested_data_structures(): void
    {
        $nestedData = [
            'level1' => [
                'level2' => [
                    'level3' => ['value' => 'deep'],
                    'array' => [1, 2, 3],
                ],
                'mixed' => ['string', 123, true, null],
            ],
        ];

        $content = new SamplingContent('nested', null, $nestedData);
        $this->assertSame($nestedData, $content->getData());

        // Verify round trip preserves nested structure
        $array = $content->toArray();
        $restored = SamplingContent::fromArray($array);
        $this->assertSame($nestedData, $restored->getData());
    }
}
