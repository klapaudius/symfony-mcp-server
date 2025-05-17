<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Server\Request\ToolsListHandler;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;
use PHPUnit\Framework\TestCase;

class ToolsListHandlerTest extends TestCase
{
    private ToolRepository $toolRepository;
    private ToolsListHandler $toolsListHandler;

    protected function setUp(): void
    {
        $this->toolRepository = $this->createMock(ToolRepository::class);
        $this->toolsListHandler = new ToolsListHandler($this->toolRepository);
    }

    /**
     * Tests that the execute function returns the correct tool schemas.
     */
    public function test_execute_returns_tool_schemas(): void
    {
        $expectedSchemas = (new HelloWorldTool)->getInputSchema();

        $this->toolRepository
            ->expects($this->once())
            ->method('getToolSchemas')
            ->willReturn($expectedSchemas);

        $result = $this->toolsListHandler->execute('tools/list', 1, null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tools', $result);
        $this->assertEquals($expectedSchemas, $result['tools']);
    }

    /**
     * Tests whether the isHandle method correctly returns true for the 'tools/list' handle.
     */
    public function test_is_handle_returns_true_for_tools_list(): void
    {
        $result = $this->toolsListHandler->isHandle('tools/list');

        $this->assertTrue($result);
    }

    /**
     * Tests whether the isHandle method correctly returns false for non-matching handles.
     */
    public function test_is_handle_returns_false_for_other_methods(): void
    {
        $result = $this->toolsListHandler->isHandle('other/method');

        $this->assertFalse($result);
    }
}
