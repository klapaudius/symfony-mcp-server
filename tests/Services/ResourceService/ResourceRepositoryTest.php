<?php

namespace KLP\KlpMcpServer\Tests\Services\ResourceService;

use InvalidArgumentException;
use KLP\KlpMcpServer\Services\ResourceService\Examples\McpDocumentationResource;
use KLP\KlpMcpServer\Services\ResourceService\Resource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;
use KLP\KlpMcpServer\Services\ResourceService\ResourceTemplateInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

#[Small]
class ResourceRepositoryTest extends TestCase
{
    private ContainerInterface $container;

    private ResourceRepository $resourceRepository;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->resourceRepository = new ResourceRepository($this->container);
    }

    /**
     * Tests that the constructor registers resources from the container's parameters.
     */
    public function test_construct_registers_resources_from_container(): void
    {
        $resource1 = $this->createMock(ResourceInterface::class);
        $resource1->method('getUri')->willReturn('resource1');

        $resource2 = $this->createMock(ResourceInterface::class);
        $resource2->method('getUri')->willReturn('resource2');

        $invocations = ['klp_mcp_server.resources', 'klp_mcp_server.resources_templates'];
        $this->container
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('getParameter')
            ->with($this->callback(function ($parameter) use ($invocations, $matcher) {
                $this->assertEquals($invocations[$matcher->numberOfInvocations() - 1], $parameter);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                ['ResourceClass1', 'ResourceClass2'],
                null
            );

        $this->container->method('get')->willReturnMap([
            ['ResourceClass1', $resource1],
            ['ResourceClass2', $resource2],
        ]);

        $repository = new ResourceRepository($this->container);

        $resources = $repository->getResources();

        $this->assertCount(2, $resources);
        $this->assertSame($resource1, $resources['resource1']);
        $this->assertSame($resource2, $resources['resource2']);
    }

    /**
     * Tests that the constructor registers resources templates from the container's parameters.
     */
    public function test_construct_registers_resources_templates_from_container(): void
    {
        $resourceTemplate = $this->createMock(ResourceTemplateInterface::class);
        $resource = $this->createMock(Resource::class);
        $resource->method('getUri')->willReturn('file:/docs/test.md');
        $resourceTemplate->method('getResource')->with('file:/docs/test.md')->willReturn($resource);
        $resourceTemplate->method('getUriTemplate')->willReturn('file:/docs/{filename}.md');

        $invocations = [
            'klp_mcp_server.resources',
            'klp_mcp_server.resources_templates',
        ];
        $this->container
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('getParameter')
            ->with($this->callback(function ($parameter) use ($invocations, $matcher) {
                $this->assertEquals($invocations[$matcher->numberOfInvocations() - 1], $parameter);

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                null,
                [McpDocumentationResource::class]
            );

        $this->container
            ->expects($this->once())
            ->method('get')
            ->with(McpDocumentationResource::class)
            ->willReturn($resourceTemplate);

        $repository = new ResourceRepository($this->container);

        // First, verify that the resource template was registered
        $this->assertCount(1, $repository->getResourceTemplateSchemas());

        // Then, load the resource and verify it was loaded correctly
        $loadedResource = $repository->getResource('file:/docs/test.md');
        $this->assertSame($resource, $loadedResource);

        // Finally, verify that the resource is now in the resources array
        $resources = $repository->getResources();
        $this->assertCount(1, $resources);
        $this->assertSame($resource, $resources['file:/docs/test.md']);
    }

    /**
     * Tests that the constructor does nothing if no resources are defined in the container's parameters.
     */
    public function test_construct_with_no_resources_defined(): void
    {
        $invocations = [
            'klp_mcp_server.resources',
            'klp_mcp_server.resources_templates',
        ];
        $this->container
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('getParameter')
            ->with($this->callback(function ($parameter) use ($invocations, $matcher) {
                $this->assertEquals($invocations[$matcher->numberOfInvocations() - 1], $parameter);

                return true;
            }))
            ->willReturn(null);

        $repository = new ResourceRepository($this->container);

        $this->assertEmpty($repository->getResources());
    }

    /**
     * Tests that multiple valid resource instances can be registered successfully.
     */
    public function test_register_many_with_valid_resource_instances(): void
    {
        $resource1 = $this->createMock(ResourceInterface::class);
        $resource1->method('getUri')->willReturn('resource1');

        $resource2 = $this->createMock(ResourceInterface::class);
        $resource2->method('getUri')->willReturn('resource2');

        $this->resourceRepository->registerMany([$resource1, $resource2]);

        $resources = $this->resourceRepository->getResources();

        $this->assertCount(2, $resources);
        $this->assertSame($resource1, $resources['resource1']);
        $this->assertSame($resource2, $resources['resource2']);
    }

    /**
     * Tests that resources can be registered by their service class names.
     */
    public function test_register_many_with_valid_resource_class_names(): void
    {
        $resource1 = $this->createMock(ResourceInterface::class);
        $resource1->method('getUri')->willReturn('resource1');

        $this->container
            ->method('get')
            ->with('ResourceClass1')
            ->willReturn($resource1);

        $this->resourceRepository->registerMany(['ResourceClass1']);

        $resources = $this->resourceRepository->getResources();

        $this->assertCount(1, $resources);
        $this->assertSame($resource1, $resources['resource1']);
    }

    /**
     * Tests that an exception is thrown when trying to register an invalid resource.
     */
    public function test_register_many_with_invalid_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Resource must implement the '.ResourceInterface::class);

        $this->resourceRepository->registerMany(['InvalidResourceClass']);
    }

    /**
     * Tests that a ServiceNotFoundException is properly propagated when a service is not found.
     */
    public function test_register_many_throws_service_not_found_exception(): void
    {
        $this->container
            ->method('get')
            ->willThrowException(new ServiceNotFoundException('ResourceClass1'));

        $this->expectException(ServiceNotFoundException::class);

        $this->resourceRepository->registerMany(['ResourceClass1']);
    }

    /**
     * Tests that getResource returns the resource if it's already loaded.
     */
    public function test_get_resource_returns_loaded_resource(): void
    {
        $resource = $this->createMock(ResourceInterface::class);
        $resource->method('getUri')->willReturn('resource1');

        $this->resourceRepository->register($resource);

        $this->assertSame($resource, $this->resourceRepository->getResource('resource1'));
    }

    /**
     * Tests that getResource loads a resource from a resourcetemplate if not already loaded.
     */
    public function test_get_resource_loads_from_resource_template(): void
    {
        $resourceTemplate = $this->createMock(ResourceTemplateInterface::class);
        $resource = $this->createMock(ResourceInterface::class);

        $resourceTemplate->method('getResource')->with('resource1')->willReturn($resource);
        $resource->method('getUri')->willReturn('resource1');

        $this->resourceRepository->registerResourceTemplate($resourceTemplate);

        $this->assertSame($resource, $this->resourceRepository->getResource('resource1'));
    }

    /**
     * Tests that getResource returns null when the resource is not found.
     */
    public function test_get_resource_returns_null_when_not_found(): void
    {
        $this->assertNull($this->resourceRepository->getResource('nonexistent-resource'));
    }

    /**
     * Tests that getResourceSchemas returns the correct schema for registered resources.
     */
    public function test_get_resource_schemas_returns_correct_schema(): void
    {
        $resource1 = $this->createMock(ResourceInterface::class);
        $resource1->method('getUri')->willReturn('resource1');
        $resource1->method('getName')->willReturn('Resource 1');
        $resource1->method('getDescription')->willReturn('Description 1');
        $resource1->method('getMimeType')->willReturn('application/json');

        $resource2 = $this->createMock(ResourceInterface::class);
        $resource2->method('getUri')->willReturn('resource2');
        $resource2->method('getName')->willReturn('Resource 2');
        $resource2->method('getDescription')->willReturn('Description 2');
        $resource2->method('getMimeType')->willReturn('text/plain');

        $this->resourceRepository->registerMany([$resource1, $resource2]);

        $expectedSchemas = [
            [
                'uri' => 'resource1',
                'name' => 'Resource 1',
                'description' => 'Description 1',
                'mimeType' => 'application/json',
            ],
            [
                'uri' => 'resource2',
                'name' => 'Resource 2',
                'description' => 'Description 2',
                'mimeType' => 'text/plain',
            ],
        ];

        $this->assertEquals($expectedSchemas, $this->resourceRepository->getResourceSchemas());
    }

    /**
     * Tests that getResourceSchemas returns an empty array when no resources are registered.
     */
    public function test_get_resource_schemas_with_no_resources(): void
    {
        $this->assertEmpty($this->resourceRepository->getResourceSchemas());
    }

    /**
     * Tests that getResourceTemplateSchemas returns the correct schema for registered resource templates.
     */
    public function test_get_resource_template_schemas_returns_correct_schema(): void
    {
        $resourceTemplate1 = $this->createMock(ResourceTemplateInterface::class);
        $resourceTemplate1->method('getUriTemplate')->willReturn('file:/docs/{filename}.md');
        $resourceTemplate1->method('getName')->willReturn('Documentation');
        $resourceTemplate1->method('getDescription')->willReturn('MCP Documentation');
        $resourceTemplate1->method('getMimeType')->willReturn('text/markdown');

        $resourceTemplate2 = $this->createMock(ResourceTemplateInterface::class);
        $resourceTemplate2->method('getUriTemplate')->willReturn('file:/images/{filename}.png');
        $resourceTemplate2->method('getName')->willReturn('Images');
        $resourceTemplate2->method('getDescription')->willReturn('Image Resources');
        $resourceTemplate2->method('getMimeType')->willReturn('image/png');

        $this->resourceRepository->registerResourceTemplate($resourceTemplate1);
        $this->resourceRepository->registerResourceTemplate($resourceTemplate2);

        $expectedSchemas = [
            [
                'uriTemplate' => 'file:/docs/{filename}.md',
                'name' => 'Documentation',
                'description' => 'MCP Documentation',
                'mimeType' => 'text/markdown',
            ],
            [
                'uriTemplate' => 'file:/images/{filename}.png',
                'name' => 'Images',
                'description' => 'Image Resources',
                'mimeType' => 'image/png',
            ],
        ];

        $this->assertEquals($expectedSchemas, $this->resourceRepository->getResourceTemplateSchemas());
    }

    /**
     * Tests that getResourceTemplateSchemas returns an empty array when no resource templates are registered.
     */
    public function test_get_resource_template_schemas_with_no_templates(): void
    {
        $this->assertEmpty($this->resourceRepository->getResourceTemplateSchemas());
    }
}
