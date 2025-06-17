<?php

namespace KLP\KlpMcpServer\Tests\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\ResourceService\Examples\HelloWorldResource;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Test class for HelloWorldResource.
 *
 * This class contains tests for the getUri method of the HelloWorldResource class.
 */
#[Small]
class HelloWorldResourceTest extends TestCase
{
    /**
     * Test to check if getUri method returns the correct URI.
     */
    public function test_get_uri_returns_correct_uri(): void
    {
        $resource = new HelloWorldResource();
        $expectedUri = 'file:/hello-world.txt';

        $this->assertSame($expectedUri, $resource->getUri());
    }

    /**
     * Test to check if getName method returns the correct name.
     */
    public function test_get_name_returns_correct_name(): void
    {
        $resource = new HelloWorldResource();
        $expectedName = 'hello-world.txt';

        $this->assertSame($expectedName, $resource->getName());
    }

    /**
     * Test to check if getDescription method returns the correct description.
     */
    public function test_get_description_returns_correct_description(): void
    {
        $resource = new HelloWorldResource();
        $expectedDescription = 'The HelloWorld resource.';

        $this->assertSame($expectedDescription, $resource->getDescription());
    }

    /**
     * Test to check if getMimeType method returns the correct MIME type.
     */
    public function test_get_mime_type_returns_correct_mime_type(): void
    {
        $resource = new HelloWorldResource();
        $expectedMimeType = 'text/plain';

        $this->assertSame($expectedMimeType, $resource->getMimeType());
    }

    /**
     * Test to check if getData method returns the correct data.
     */
    public function test_get_data_returns_correct_data(): void
    {
        $resource = new HelloWorldResource();
        $expectedData = 'Hello, World!';

        $this->assertSame($expectedData, $resource->getData());
    }

    /**
     * Test to check if getSize method returns the correct size.
     */
    public function test_get_size_returns_correct_size(): void
    {
        $resource = new HelloWorldResource();
        $expectedSize = strlen('Hello, World!');

        $this->assertSame($expectedSize, $resource->getSize());
    }
}
