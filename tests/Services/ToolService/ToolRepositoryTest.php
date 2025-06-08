<?php

namespace KLP\KlpMcpServer\Tests\Services\ToolService;

use InvalidArgumentException;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

#[Small]
class ToolRepositoryTest extends TestCase
{
    private ContainerInterface|MockObject $container;

    private ToolRepository $toolRepository;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->toolRepository = new ToolRepository($this->container);
    }

    /**
     * Tests the registration of multiple valid tool instances in the tool repository.
     *
     * Validates that the tool repository correctly registers multiple tool instances
     * and ensures that the tools can be retrieved and matched against their original instances.
     */
    public function test_register_many_with_valid_tool_instances(): void
    {
        $tool1 = $this->createMock(StreamableToolInterface::class);
        $tool2 = $this->createMock(StreamableToolInterface::class);

        $tool1->method('getName')->willReturn('tool1');
        $tool2->method('getName')->willReturn('tool2');

        $this->toolRepository->registerMany([$tool1, $tool2]);

        $tools = $this->toolRepository->getTools();

        $this->assertCount(2, $tools);
        $this->assertSame($tool1, $tools['tool1']);
        $this->assertSame($tool2, $tools['tool2']);
    }

    /**
     * Tests the registration of multiple valid tool instances in the tool repository.
     *
     * Validates that the tool repository correctly registers multiple tool instances
     * and ensures that the tools can be retrieved and matched against their original instances.
     */
    public function test_register_many_is_called_on_constructor(): void
    {
        $tool1 = $this->createMock(StreamableToolInterface::class);
        $tool2 = $this->createMock(StreamableToolInterface::class);

        $tool1->method('getName')->willReturn('tool1');
        $tool2->method('getName')->willReturn('tool2');
        $invocations = ['tool1', 'tool2'];
        $this->container
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('get')
            ->with($this->callback(function ($toolClass) use ($invocations, $matcher) {
                $this->assertEquals($invocations[$matcher->numberOfInvocations() - 1], $toolClass);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                $tool1,
                $tool2
            );
        $this->container
            ->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.tools')
            ->willReturn(['tool1', 'tool2']);

        $toolRepository = new ToolRepository($this->container);

        $tools = $toolRepository->getTools();

        $this->assertCount(2, $tools);
        $this->assertSame($tool1, $tools['tool1']);
        $this->assertSame($tool2, $tools['tool2']);
        $this->assertSame($tool1, $toolRepository->getTool('tool1'));

    }

    /**
     * Tests the registration of multiple tools using valid tool class names.
     *
     * This method verifies that the `registerMany` function correctly retrieves and registers tools
     * based on their corresponding class names, ensuring they are available in the repository.
     * It also asserts that the tools are correctly mapped in the repository by their respective names.
     */
    public function test_register_many_with_valid_tool_class_names(): void
    {
        $tool1 = $this->createMock(StreamableToolInterface::class);
        $tool2 = $this->createMock(StreamableToolInterface::class);

        $tool1->method('getName')->willReturn('tool1');
        $tool2->method('getName')->willReturn('tool2');

        $this->container
            ->method('get')
            ->willReturnMap([
                ['Tool1Class', $tool1],
                ['Tool2Class', $tool2],
            ]);

        $this->toolRepository->registerMany(['Tool1Class', 'Tool2Class']);

        $tools = $this->toolRepository->getTools();

        $this->assertCount(2, $tools);
        $this->assertSame($tool1, $tools['tool1']);
        $this->assertSame($tool2, $tools['tool2']);
    }

    /**
     * Tests the behavior of the `registerMany` method when provided with invalid tool class names.
     *
     * This method ensures that an exception is thrown when attempting to register tools with invalid
     * or non-existent class names, verifying that error handling works as expected.
     */
    public function test_register_many_throws_exception_for_invalid_tool(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->toolRepository->registerMany(['InvalidToolClass']);
    }

    /**
     * Tests that the registerMany() method throws a ServiceNotFoundException
     * when a requested container service is not found.
     */
    public function test_register_many_throws_exception_for_container_service_not_found(): void
    {
        $this->container
            ->method('get')
            ->willThrowException(new ServiceNotFoundException('ToolService'));

        $this->expectException(ServiceNotFoundException::class);

        $this->toolRepository->registerMany(['NonExistentToolClass']);
    }

    /**
     * Tests that the registerMany() method throws a ServiceCircularReferenceException
     * when a circular reference is detected in the requested container service.
     */
    public function test_register_many_throws_exception_for_service_circular_reference(): void
    {
        $this->container
            ->method('get')
            ->willThrowException(new ServiceCircularReferenceException('ToolService', []));

        $this->expectException(ServiceCircularReferenceException::class);

        $this->toolRepository->registerMany(['CircularReferenceToolClass']);
    }

    /**
     * Tests that getToolSchemas() returns valid schemas for registered tools.
     *
     * Verifies that the method correctly builds an array of tool schemas based
     * on the registered tools, including their names, descriptions, and input schemas.
     */
    public function test_get_tool_schemas_returns_valid_schemas(): void
    {
        $tool1 = $this->createMock(StreamableToolInterface::class);
        $tool2 = $this->createMock(StreamableToolInterface::class);

        $tool1->method('getName')->willReturn('tool1');
        $tool1->method('getDescription')->willReturn('Description for tool1');
        $tool1->method('getInputSchema')->willReturn(['type' => 'object']);

        $tool2->method('getName')->willReturn('tool2');
        $tool2->method('getDescription')->willReturn('Description for tool2');
        $tool2->method('getInputSchema')->willReturn(['type' => 'array']);

        $this->toolRepository->registerMany([$tool1, $tool2]);

        $schemas = $this->toolRepository->getToolSchemas();

        $this->assertCount(2, $schemas);

        $this->assertSame('tool1', $schemas[0]['name']);
        $this->assertSame('Description for tool1', $schemas[0]['description']);
        $this->assertSame(['type' => 'object'], $schemas[0]['inputSchema']);

        $this->assertSame('tool2', $schemas[1]['name']);
        $this->assertSame('Description for tool2', $schemas[1]['description']);
        $this->assertSame(['type' => 'array'], $schemas[1]['inputSchema']);
    }

    /**
     * Tests that getToolSchemas() handles tools with empty input schema correctly.
     *
     * Verifies that the method sets a default input schema for tools that do not
     * define one, ensuring a consistent output structure.
     */
    public function test_get_tool_schemas_handles_tools_with_empty_input_schema(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $tool->method('getName')->willReturn('tool1');
        $tool->method('getDescription')->willReturn('Description for tool1');
        $tool->method('getInputSchema')->willReturn([]);

        $this->toolRepository->register($tool);

        $schemas = $this->toolRepository->getToolSchemas();

        $this->assertCount(1, $schemas);
        $this->assertSame('tool1', $schemas[0]['name']);
        $this->assertEquals(['type' => 'object', 'properties' => new stdClass, 'required' => []], $schemas[0]['inputSchema']);
    }

    /**
     * Tests that getToolSchemas() includes annotations if they are present in a tool.
     *
     * Verifies that the method appends the annotations to the tool schema, ensuring
     * that metadata is preserved and available for the MCP capabilities response.
     */
    public function test_get_tool_schemas_includes_annotations_if_present(): void
    {
        $tool = $this->createMock(StreamableToolInterface::class);

        $tool->method('getName')->willReturn('toolWithAnnotations');
        $tool->method('getDescription')->willReturn('Tool with annotations');
        $tool->method('getInputSchema')->willReturn(['type' => 'object']);
        $tool->method('getAnnotations')->willReturn(new ToolAnnotation);

        $this->toolRepository->register($tool);

        $schemas = $this->toolRepository->getToolSchemas();

        $this->assertCount(1, $schemas);
        $this->assertSame('toolWithAnnotations', $schemas[0]['name']);
        $this->assertSame('Tool with annotations', $schemas[0]['description']);
        $this->assertSame(['type' => 'object'], $schemas[0]['inputSchema']);
        $this->assertSame([
            'title' => '-',
            'readOnlyHint' => false,
            'destructiveHint' => true,
            'idempotentHint' => false,
            'openWorldHint' => true,
        ], $schemas[0]['annotations']);
    }
}
