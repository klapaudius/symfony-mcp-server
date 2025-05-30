<?php

namespace KLP\KlpMcpServer\Tests\Services\ResourceService;

use KLP\KlpMcpServer\Services\ResourceService\Examples\McpDocumentationResource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

#[Small]
class McpDocumentationResourceTest extends TestCase
{
    private KernelInterface $kernel;
    private McpDocumentationResource $resource;
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = '/tmp/mcp-docs';

        // Create mock kernel
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->kernel->method('getProjectDir')
            ->willReturn('/tmp');

        // Create the resource
        $this->resource = new McpDocumentationResource($this->kernel);

        // Use reflection to set the baseDir property
        $reflectionClass = new \ReflectionClass(McpDocumentationResource::class);
        $property = $reflectionClass->getProperty('baseDir');
        $property->setAccessible(true);
        $property->setValue($this->resource, $this->baseDir);

        // Create test directory and files
        if (!file_exists($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }

        // Create test markdown files
        file_put_contents($this->baseDir . '/test1.md', "# Test Document 1\nThis is a test document.");
        file_put_contents($this->baseDir . '/test2.md', "# Test Document 2\nThis is another test document.");
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->baseDir . '/test1.md')) {
            unlink($this->baseDir . '/test1.md');
        }
        if (file_exists($this->baseDir . '/test2.md')) {
            unlink($this->baseDir . '/test2.md');
        }
        if (file_exists($this->baseDir)) {
            rmdir($this->baseDir);
        }
    }

    /**
     * Tests that the URI template is correctly formatted for documentation files
     */
    public function test_get_uri_template(): void
    {
        $this->assertSame("file:/docs/{filename}.md", $this->resource->getUriTemplate());
    }

    /**
     * Tests that the resource name is correctly returned
     */
    public function test_get_name(): void
    {
        $this->assertSame("documentation.md", $this->resource->getName());
    }

    /**
     * Tests that the description contains expected content including references to test files
     */
    public function test_get_description(): void
    {
        $description = $this->resource->getDescription();
        $this->assertStringContainsString("The MCP Documentation resources", $description);
        $this->assertStringContainsString("test1", $description);
        $this->assertStringContainsString("test2", $description);
    }

    /**
     * Tests that the MIME type is correctly returned as plain text
     */
    public function test_get_mime_type(): void
    {
        $this->assertSame("text/plain", $this->resource->getMimeType());
    }

    /**
     * Tests that a resource can be correctly retrieved and contains expected properties
     */
    public function test_get_resource(): void
    {
        $uri = "file:/docs/test1.md";
        $resource = $this->resource->getResource($uri);

        $this->assertInstanceOf(ResourceInterface::class, $resource);
        $this->assertSame($uri, $resource->getUri());
        $this->assertSame("test1.md", $resource->getName());
        $this->assertSame("# Test Document 1", $resource->getDescription());
        $this->assertStringContainsString("text/", $resource->getMimeType());
        $this->assertSame("# Test Document 1\nThis is a test document.", $resource->getData());
    }

    /**
     * Tests that null is returned when attempting to retrieve a resource with an invalid URI
     */
    public function test_get_resource_with_invalid_uri(): void
    {
        $uri = "file:/docs/nonexistent.md";
        $resource = $this->resource->getResource($uri);

        $this->assertNull($resource);
    }

    /**
     * Tests that the resource existence check correctly identifies valid and invalid resources
     */
    public function test_resource_exists(): void
    {
        $this->assertTrue($this->resource->resourceExists("file:/docs/test1.md"));
        $this->assertTrue($this->resource->resourceExists("file:/docs/test2.md"));
        $this->assertFalse($this->resource->resourceExists("file:/docs/nonexistent.md"));
        $this->assertFalse($this->resource->resourceExists("invalid-uri"));
    }

    /**
     * Tests that filenames are correctly extracted from URIs
     */
    public function test_get_filename_from_uri(): void
    {
        // Use reflection to access private method
        $reflectionClass = new \ReflectionClass(McpDocumentationResource::class);
        $method = $reflectionClass->getMethod('getFilenameFromUri');
        $method->setAccessible(true);

        $this->assertSame("test1", $method->invoke($this->resource, "file:/docs/test1.md"));
        $this->assertSame("test2", $method->invoke($this->resource, "file:/docs/test2.md"));
        $this->assertNull($method->invoke($this->resource, "invalid-uri"));
    }

    /**
     * Tests that the MIME type is correctly guessed from a file
     */
    public function test_guess_mime_type(): void
    {
        // Use reflection to access protected method
        $reflectionClass = new \ReflectionClass(McpDocumentationResource::class);
        $method = $reflectionClass->getMethod('guessMimeType');
        $method->setAccessible(true);

        $mimeType = $method->invoke($this->resource, $this->baseDir . '/test1.md');
        $this->assertStringContainsString("text/", $mimeType);
    }
}
