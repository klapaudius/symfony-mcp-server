<?php

namespace KLP\KlpMcpServer\Tests\Services\ResourceService;

use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceProviderInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Small]
class ResourceProviderTest extends TestCase
{
    private ContainerInterface|MockObject $container;

    private ResourceRepository $resourceRepository;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->resourceRepository = new ResourceRepository($this->container);
    }

    /**
     * Tests that registerProvider() correctly registers resources from a ResourceProviderInterface.
     *
     * Verifies that the method calls getResources() on the provider and registers each
     * returned resource with the repository.
     */
    public function test_register_provider_registers_resources_from_provider(): void
    {
        $resource1 = $this->createMock(ResourceInterface::class);
        $resource2 = $this->createMock(ResourceInterface::class);

        $resource1->method('getUri')->willReturn('file:/provider-resource1.txt');
        $resource2->method('getUri')->willReturn('file:/provider-resource2.txt');

        $provider = $this->createMock(ResourceProviderInterface::class);
        $provider->expects($this->once())
            ->method('getResources')
            ->willReturn([$resource1, $resource2]);

        $this->resourceRepository->registerProvider($provider);

        $resources = $this->resourceRepository->getResources();

        $this->assertCount(2, $resources);
        $this->assertSame($resource1, $resources['file:/provider-resource1.txt']);
        $this->assertSame($resource2, $resources['file:/provider-resource2.txt']);
    }

    /**
     * Tests that registerProvider() works with resource class names from provider.
     *
     * Verifies that when a provider returns resource class names (strings),
     * they are correctly resolved from the container and registered.
     */
    public function test_register_provider_with_resource_class_names(): void
    {
        $resource1 = $this->createMock(ResourceInterface::class);
        $resource2 = $this->createMock(ResourceInterface::class);

        $resource1->method('getUri')->willReturn('file:/resource-from-class1.txt');
        $resource2->method('getUri')->willReturn('file:/resource-from-class2.txt');

        $this->container
            ->method('get')
            ->willReturnMap([
                ['ResourceClass1', $resource1],
                ['ResourceClass2', $resource2],
            ]);

        $provider = $this->createMock(ResourceProviderInterface::class);
        $provider->expects($this->once())
            ->method('getResources')
            ->willReturn(['ResourceClass1', 'ResourceClass2']);

        $this->resourceRepository->registerProvider($provider);

        $resources = $this->resourceRepository->getResources();

        $this->assertCount(2, $resources);
        $this->assertSame($resource1, $resources['file:/resource-from-class1.txt']);
        $this->assertSame($resource2, $resources['file:/resource-from-class2.txt']);
    }

    /**
     * Tests that registerProvider() can be chained with other registration methods.
     *
     * Verifies that resources from YAML config, direct registration, and providers
     * all coexist in the repository.
     */
    public function test_register_provider_works_alongside_other_registration_methods(): void
    {
        $directResource = $this->createMock(ResourceInterface::class);
        $providerResource = $this->createMock(ResourceInterface::class);

        $directResource->method('getUri')->willReturn('file:/direct-resource.txt');
        $providerResource->method('getUri')->willReturn('file:/provider-resource.txt');

        // Register directly
        $this->resourceRepository->register($directResource);

        // Register via provider
        $provider = $this->createMock(ResourceProviderInterface::class);
        $provider->method('getResources')->willReturn([$providerResource]);
        $this->resourceRepository->registerProvider($provider);

        $resources = $this->resourceRepository->getResources();

        $this->assertCount(2, $resources);
        $this->assertSame($directResource, $resources['file:/direct-resource.txt']);
        $this->assertSame($providerResource, $resources['file:/provider-resource.txt']);
    }

    /**
     * Tests that registerProvider() handles empty resource list from provider.
     *
     * Verifies that when a provider returns an empty array, it doesn't cause errors.
     */
    public function test_register_provider_handles_empty_resource_list(): void
    {
        $provider = $this->createMock(ResourceProviderInterface::class);
        $provider->expects($this->once())
            ->method('getResources')
            ->willReturn([]);

        $this->resourceRepository->registerProvider($provider);

        $resources = $this->resourceRepository->getResources();

        $this->assertCount(0, $resources);
    }
}
