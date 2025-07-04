<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use PHPUnit\Framework\TestCase;

class ModelPreferencesTest extends TestCase
{
    public function testCreateModelPreferencesWithAllFields(): void
    {
        $hints = [
            ['name' => 'claude-3-sonnet'],
            ['name' => 'claude-3-opus'],
        ];

        $preferences = new ModelPreferences(
            $hints,
            0.7,  // costPriority
            0.8,  // speedPriority
            0.9   // intelligencePriority
        );

        $this->assertSame($hints, $preferences->getHints());
        $this->assertSame(0.7, $preferences->getCostPriority());
        $this->assertSame(0.8, $preferences->getSpeedPriority());
        $this->assertSame(0.9, $preferences->getIntelligencePriority());
    }

    public function testCreateModelPreferencesWithDefaults(): void
    {
        $preferences = new ModelPreferences();

        $this->assertSame([], $preferences->getHints());
        $this->assertNull($preferences->getCostPriority());
        $this->assertNull($preferences->getSpeedPriority());
        $this->assertNull($preferences->getIntelligencePriority());
    }

    public function testCreateModelPreferencesWithPartialData(): void
    {
        $hints = [['name' => 'claude-3-haiku']];
        $preferences = new ModelPreferences(
            hints: $hints,
            costPriority: 0.5
        );

        $this->assertSame($hints, $preferences->getHints());
        $this->assertSame(0.5, $preferences->getCostPriority());
        $this->assertNull($preferences->getSpeedPriority());
        $this->assertNull($preferences->getIntelligencePriority());
    }

    public function testToArrayWithAllFields(): void
    {
        $hints = [
            ['name' => 'model-1', 'version' => 'latest'],
            ['name' => 'model-2'],
        ];

        $preferences = new ModelPreferences(
            $hints,
            0.3,  // costPriority
            0.4,  // speedPriority
            0.5   // intelligencePriority
        );

        $array = $preferences->toArray();

        $this->assertArrayHasKey('hints', $array);
        $this->assertArrayHasKey('costPriority', $array);
        $this->assertArrayHasKey('speedPriority', $array);
        $this->assertArrayHasKey('intelligencePriority', $array);

        $this->assertSame($hints, $array['hints']);
        $this->assertSame(0.3, $array['costPriority']);
        $this->assertSame(0.4, $array['speedPriority']);
        $this->assertSame(0.5, $array['intelligencePriority']);
    }

    public function testToArrayWithEmptyData(): void
    {
        $preferences = new ModelPreferences();
        $array = $preferences->toArray();

        $this->assertSame([], $array);
    }

    public function testToArrayWithPartialData(): void
    {
        $preferences = new ModelPreferences(
            hints: [['name' => 'test-model']],
            intelligencePriority: 0.95
        );

        $array = $preferences->toArray();

        $this->assertArrayHasKey('hints', $array);
        $this->assertArrayNotHasKey('costPriority', $array);
        $this->assertArrayNotHasKey('speedPriority', $array);
        $this->assertArrayHasKey('intelligencePriority', $array);

        $this->assertSame([['name' => 'test-model']], $array['hints']);
        $this->assertSame(0.95, $array['intelligencePriority']);
    }

    public function testFromArrayWithAllFields(): void
    {
        $data = [
            'hints' => [
                ['name' => 'claude-3-sonnet', 'context' => 'general'],
                ['name' => 'claude-3-opus'],
            ],
            'costPriority' => 0.2,
            'speedPriority' => 0.6,
            'intelligencePriority' => 0.8,
        ];

        $preferences = ModelPreferences::fromArray($data);

        $this->assertSame($data['hints'], $preferences->getHints());
        $this->assertSame(0.2, $preferences->getCostPriority());
        $this->assertSame(0.6, $preferences->getSpeedPriority());
        $this->assertSame(0.8, $preferences->getIntelligencePriority());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $preferences = ModelPreferences::fromArray([]);

        $this->assertSame([], $preferences->getHints());
        $this->assertNull($preferences->getCostPriority());
        $this->assertNull($preferences->getSpeedPriority());
        $this->assertNull($preferences->getIntelligencePriority());
    }

    public function testFromArrayWithPartialData(): void
    {
        $data = [
            'speedPriority' => 0.75,
            'intelligencePriority' => 0.25,
        ];

        $preferences = ModelPreferences::fromArray($data);

        $this->assertSame([], $preferences->getHints());
        $this->assertNull($preferences->getCostPriority());
        $this->assertSame(0.75, $preferences->getSpeedPriority());
        $this->assertSame(0.25, $preferences->getIntelligencePriority());
    }

    public function testRoundTripConversion(): void
    {
        $originalData = [
            'hints' => [
                ['name' => 'model-alpha', 'tier' => 'premium'],
                ['name' => 'model-beta'],
            ],
            'costPriority' => 0.1,
            'speedPriority' => 0.2,
            'intelligencePriority' => 0.3,
        ];

        $preferences = ModelPreferences::fromArray($originalData);
        $convertedData = $preferences->toArray();

        $this->assertSame($originalData, $convertedData);
    }

    public function testPriorityValueEdgeCases(): void
    {
        // Test with zero priorities
        $preferences = new ModelPreferences(
            [],
            0.0,  // costPriority
            0.0,  // speedPriority
            0.0   // intelligencePriority
        );

        $this->assertSame(0.0, $preferences->getCostPriority());
        $this->assertSame(0.0, $preferences->getSpeedPriority());
        $this->assertSame(0.0, $preferences->getIntelligencePriority());

        // Test with max priorities
        $preferences = new ModelPreferences(
            [],
            1.0,  // costPriority
            1.0,  // speedPriority
            1.0   // intelligencePriority
        );

        $this->assertSame(1.0, $preferences->getCostPriority());
        $this->assertSame(1.0, $preferences->getSpeedPriority());
        $this->assertSame(1.0, $preferences->getIntelligencePriority());
    }
}