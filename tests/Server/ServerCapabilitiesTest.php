<?php

namespace KLP\KlpMcpServer\Tests\Server;

use KLP\KlpMcpServer\Server\ServerCapabilities;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use stdClass;

#[Small]
class ServerCapabilitiesTest extends TestCase
{
    /**
     * Tests the default output of the toInitializeMessage method
     * when no tools have been enabled.
     */
    public function test_to_initialize_message_without_tools(): void
    {
        $serverCapabilities = new ServerCapabilities;
        $initializeMessage = $serverCapabilities->toInitializeMessage();

        $this->assertArrayHasKey('prompts', $initializeMessage);
        $this->assertArrayHasKey('resources', $initializeMessage);
        $this->assertArrayHasKey('tools', $initializeMessage);

        $this->assertInstanceOf(stdClass::class, $initializeMessage['prompts']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['resources']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['tools']);

        $this->assertObjectNotHasProperty('listChanged', $initializeMessage['tools']);
    }

    /**
     * Tests the output of the toInitializeMessage method
     * when tools support is enabled without additional configuration.
     */
    public function test_to_initialize_message_with_tools_no_config(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withTools();
        $initializeMessage = $serverCapabilities->toInitializeMessage();

        $this->assertArrayHasKey('prompts', $initializeMessage);
        $this->assertArrayHasKey('resources', $initializeMessage);
        $this->assertArrayHasKey('tools', $initializeMessage);

        $this->assertInstanceOf(stdClass::class, $initializeMessage['prompts']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['resources']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['tools']);

        $this->assertObjectHasProperty('listChanged', $initializeMessage['tools']);
        $this->assertTrue($initializeMessage['tools']->listChanged);
    }

    /**
     * Tests the output of the toInitializeMessage method
     * when tools support is enabled with a configuration.
     */
    public function test_to_initialize_message_with_tools_and_config(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withTools(['testConfig' => 'value']);
        $initializeMessage = $serverCapabilities->toInitializeMessage();

        $this->assertArrayHasKey('prompts', $initializeMessage);
        $this->assertArrayHasKey('resources', $initializeMessage);
        $this->assertArrayHasKey('tools', $initializeMessage);

        $this->assertInstanceOf(stdClass::class, $initializeMessage['prompts']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['resources']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['tools']);

        $this->assertObjectHasProperty('listChanged', $initializeMessage['tools']);
        $this->assertTrue($initializeMessage['tools']->listChanged);
    }

    /**
     * Tests the output of the toArray method
     * when tools support is not enabled.
     */
    public function test_to_array_without_tools(): void
    {
        $serverCapabilities = new ServerCapabilities;
        $arrayOutput = $serverCapabilities->toArray();

        $this->assertArrayHasKey('prompts', $arrayOutput);
        $this->assertArrayHasKey('resources', $arrayOutput);
        $this->assertArrayNotHasKey('tools', $arrayOutput);

        $this->assertInstanceOf(stdClass::class, $arrayOutput['prompts']);
        $this->assertInstanceOf(stdClass::class, $arrayOutput['resources']);
    }

    /**
     * Tests the output of the toArray method
     * when tools support is enabled without additional configuration.
     */
    public function test_to_array_with_tools_no_config(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withTools();
        $arrayOutput = $serverCapabilities->toArray();

        $this->assertArrayHasKey('prompts', $arrayOutput);
        $this->assertArrayHasKey('resources', $arrayOutput);
        $this->assertArrayHasKey('tools', $arrayOutput);

        $this->assertInstanceOf(stdClass::class, $arrayOutput['prompts']);
        $this->assertInstanceOf(stdClass::class, $arrayOutput['resources']);
        $this->assertInstanceOf(stdClass::class, $arrayOutput['tools']);
    }

    /**
     * Tests the output of the toArray method
     * when tools support is enabled with a configuration.
     */
    public function test_to_array_with_tools_and_config(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withTools(['exampleConfig' => 'value']);
        $arrayOutput = $serverCapabilities->toArray();

        $this->assertArrayHasKey('prompts', $arrayOutput);
        $this->assertArrayHasKey('resources', $arrayOutput);
        $this->assertArrayHasKey('tools', $arrayOutput);

        $this->assertInstanceOf(stdClass::class, $arrayOutput['prompts']);
        $this->assertInstanceOf(stdClass::class, $arrayOutput['resources']);
        $this->assertIsArray($arrayOutput['tools']);
        $this->assertArrayHasKey('exampleConfig', $arrayOutput['tools']);
        $this->assertSame('value', $arrayOutput['tools']['exampleConfig']);
    }

    /**
     * Tests the output of the toArray method
     * when resources support is enabled.
     */
    public function test_to_array_with_resources(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withResources(['config' => 'value']);
        $arrayOutput = $serverCapabilities->toArray();

        $this->assertArrayHasKey('prompts', $arrayOutput);
        $this->assertArrayHasKey('resources', $arrayOutput);
        $this->assertArrayNotHasKey('tools', $arrayOutput);

        $this->assertInstanceOf(stdClass::class, $arrayOutput['prompts']);
        $this->assertIsArray($arrayOutput['resources']);
        $this->assertArrayHasKey('config', $arrayOutput['resources']);
        $this->assertSame('value', $arrayOutput['resources']['config']);
    }

    /**
     * Tests the output of the toArray method
     * when prompts support is enabled.
     */
    public function test_to_array_with_prompts(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withPrompts(['config' => 'value']);
        $arrayOutput = $serverCapabilities->toArray();

        $this->assertArrayHasKey('prompts', $arrayOutput);
        $this->assertArrayHasKey('resources', $arrayOutput);
        $this->assertArrayNotHasKey('tools', $arrayOutput);

        $this->assertIsArray($arrayOutput['prompts']);
        $this->assertArrayHasKey('config', $arrayOutput['prompts']);
        $this->assertSame('value', $arrayOutput['prompts']['config']);
        $this->assertInstanceOf(stdClass::class, $arrayOutput['resources']);
    }

    /**
     * Tests the output of the toInitializeMessage method
     * when resources support is enabled.
     */
    public function test_to_initialize_message_with_resources(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withResources();
        $initializeMessage = $serverCapabilities->toInitializeMessage();

        $this->assertArrayHasKey('prompts', $initializeMessage);
        $this->assertArrayHasKey('resources', $initializeMessage);
        $this->assertArrayHasKey('tools', $initializeMessage);

        $this->assertInstanceOf(stdClass::class, $initializeMessage['prompts']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['resources']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['tools']);

        $this->assertObjectHasProperty('listChanged', $initializeMessage['resources']);
        $this->assertTrue($initializeMessage['resources']->listChanged);
        $this->assertObjectNotHasProperty('listChanged', $initializeMessage['tools']);
    }

    /**
     * Tests the output of the toInitializeMessage method
     * when prompts support is enabled.
     */
    public function test_to_initialize_message_with_prompts(): void
    {
        $serverCapabilities = (new ServerCapabilities)->withPrompts();
        $initializeMessage = $serverCapabilities->toInitializeMessage();

        $this->assertArrayHasKey('prompts', $initializeMessage);
        $this->assertArrayHasKey('resources', $initializeMessage);
        $this->assertArrayHasKey('tools', $initializeMessage);

        $this->assertInstanceOf(stdClass::class, $initializeMessage['prompts']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['resources']);
        $this->assertInstanceOf(stdClass::class, $initializeMessage['tools']);

        $this->assertObjectHasProperty('listChanged', $initializeMessage['prompts']);
        $this->assertTrue($initializeMessage['prompts']->listChanged);
        $this->assertObjectNotHasProperty('listChanged', $initializeMessage['tools']);
    }

    /**
     * Tests the output when all capabilities are enabled.
     */
    public function test_all_capabilities_enabled(): void
    {
        $serverCapabilities = (new ServerCapabilities)
            ->withTools()
            ->withResources()
            ->withPrompts();

        $initializeMessage = $serverCapabilities->toInitializeMessage();

        $this->assertObjectHasProperty('listChanged', $initializeMessage['tools']);
        $this->assertTrue($initializeMessage['tools']->listChanged);
        $this->assertObjectHasProperty('listChanged', $initializeMessage['resources']);
        $this->assertTrue($initializeMessage['resources']->listChanged);
        $this->assertObjectHasProperty('listChanged', $initializeMessage['prompts']);
        $this->assertTrue($initializeMessage['prompts']->listChanged);
    }
}
