# Building MCP Resources - Complete Walkthrough

## Table of Contents
1. [Introduction to Resources in Model Context Protocol (MCP)](#introduction-to-resources-in-model-context-protocol-mcp)
2. [What are MCP Resources?](#what-are-mcp-resources)
3. [Types of Resources](#types-of-resources)
4. [Creating Your First MCP Resource](#creating-your-first-mcp-resource)
   - [Step 1: Create a Resource Class](#step-1-create-a-resource-class)
   - [Step 2: Register Your Resource](#step-2-register-your-resource)
     - [Option 1: Static Registration (YAML Configuration)](#option-1-static-registration-yaml-configuration)
     - [Option 2: Dynamic Registration (ResourceProvider)](#option-2-dynamic-registration-resourceprovider)
5. [Understanding the Resource Interfaces](#understanding-the-resource-interfaces)
6. [SamplingAwareResourceInterface](#samplingawareresourceinterface)
7. [Using the Resource Class](#using-the-resource-class)
8. [Advanced Resource Development](#advanced-resource-development)
9. [Best Practices for MCP Resource Development](#best-practices-for-mcp-resource-development)
10. [Example Resources](#example-resources)
11. [Conclusion](#conclusion)

## Introduction to Resources in Model Context Protocol (MCP)

Resources in the Model Context Protocol (MCP) are data objects that can be accessed by Large Language Models (LLMs) through the MCP server. Unlike tools, which provide functionality, resources provide data that LLMs can reference or use in their processing. Resources enable LLMs to:

- Access static or dynamic content
- Retrieve documentation, configuration files, or other text-based data
- Work with structured data in various formats (text, JSON, etc.)
- Reference information that may be too large or complex to include in prompts

This Symfony MCP Server implementation provides a flexible resource management system that allows you to create, register, and serve resources to LLM clients.

## What are MCP Resources?

MCP Resources are objects that:

- Have a unique URI that identifies them
- Provide metadata (name, description, MIME type)
- Contain actual data content
- Can be static (fixed content) or dynamic (generated on demand)

Resources are exposed to LLM clients through the MCP server's capabilities, allowing the LLMs to discover and request the resources they need.

## Types of Resources

The Symfony MCP Server supports two types of resources:

### 1. Standard Resources

Standard resources implement the `ResourceInterface` and provide fixed content with a specific URI. These are ideal for static content that doesn't change frequently.

### 2. Resource Templates

Resource templates implement the `ResourceTemplateInterface` and can generate multiple resources based on a URI pattern. These are useful for dynamic content or when you need to provide access to a collection of similar resources.

## Creating Your First MCP Resource

### Step 1: Create a Resource Class

Unlike tools, resources don't have a dedicated command for generation. However, you can use the provided stub file as a template:

1. Create a new PHP class in your project (e.g., in `src/MCP/Resources`)
2. Implement the `ResourceInterface`

Here's a basic example:

```php
namespace App\MCP\Resources;

use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;

class MyCustomResource implements ResourceInterface
{
    public function getUri(): string
    {
        return "file://my-custom-resource.txt";
    }

    public function getName(): string
    {
        return "my-custom-resource.txt";
    }

    public function getDescription(): string
    {
        return "A custom resource example.";
    }

    public function getMimeType(): string
    {
        return "text/plain";
    }

    public function getData(): string
    {
        return "This is my custom resource content.";
    }

    public function getSize(): int
    {
        return strlen($this->getData());
    }
}
```

### Step 2: Register Your Resource

There are two ways to register resources with the MCP server:

#### Option 1: Static Registration (YAML Configuration)

Add your resource to the configuration file:

```yaml
# config/packages/klp_mcp_server.yaml
klp_mcp_server:
    resources:
        - App\MCP\Resources\MyCustomResource
        # Add other resources here
```

This approach is simple and works well for resources that are always available in your application.

#### Option 2: Dynamic Registration (ResourceProvider)

For more flexibility, you can use a **ResourceProvider** to register resources programmatically based on runtime conditions, database configuration, feature flags, or any custom logic.

**Step 1: Create a ResourceProvider**

Implement the `ResourceProviderInterface` (recommended approach - inject resource instances):

```php
// src/MCP/Resources/Providers/MyResourceProvider.php
namespace App\MCP\Resources\Providers;

use App\MCP\Resources\MyCustomResource;
use App\MCP\Resources\DocumentationResource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceProviderInterface;

class MyResourceProvider implements ResourceProviderInterface
{
    public function __construct(
        private readonly MyCustomResource $customResource,
        private readonly DocumentationResource $docResource,
    ) {}

    public function getResources(): iterable
    {
        // Return resource instances (recommended)
        return [
            $this->customResource,
            $this->docResource,
        ];
    }
}
```

**Step 2: Register the Provider**

Register your provider as a Symfony service in `config/services.yaml`:

```yaml
services:
    App\MCP\Resources\Providers\MyResourceProvider:
        autowire: true
        autoconfigure: true  # Automatically tags with 'klp_mcp_server.resource_provider'
```

That's it! Your provider will be automatically discovered, and the resources it returns will be registered with the MCP server.

**⚠️ Important: Service Visibility**

The above example shows the **recommended approach** where you inject resource instances. If instead you return resource **class names** (strings), those resources must be registered as **public services**:

```yaml
# config/services.yaml - Only needed if returning class names (NOT recommended)
services:
    App\MCP\Resources\:
        resource: '../src/MCP/Resources/*'
        public: true
```

**Why inject instances instead of class names?**
- ✅ No need to make services public (better encapsulation)
- ✅ Better performance (resources instantiated once during container compilation)
- ✅ Type safety with constructor injection
- ✅ Follows Symfony best practices

**Advanced Example with Conditional Logic:**

```php
namespace App\MCP\Resources\Providers;

use Doctrine\ORM\EntityManagerInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceProviderInterface;

class DatabaseResourceProvider implements ResourceProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $environment,
        // Inject all potentially used resources
        private readonly MyCustomResource $customResource,
        private readonly DocumentationResource $docResource,
        private readonly DebugResource $debugResource,
        private readonly TestDataResource $testDataResource,
    ) {}

    public function getResources(): iterable
    {
        $resources = [];

        // Load resource configuration from database
        $enabledResources = $this->entityManager
            ->getRepository(ResourceConfiguration::class)
            ->findBy(['enabled' => true]);

        // Map database config to injected resource instances
        foreach ($enabledResources as $config) {
            match ($config->getUri()) {
                'file://my-custom-resource.txt' => $resources[] = $this->customResource,
                'file://documentation.md' => $resources[] = $this->docResource,
                default => null,
            };
        }

        // Add development-only resources
        if ($this->environment === 'dev') {
            $resources[] = $this->debugResource;
            $resources[] = $this->testDataResource;
        }

        return $resources;
    }
}
```

**Benefits of ResourceProviders:**

- **Dynamic Discovery**: Load resources based on database configuration, API responses, or runtime conditions
- **Conditional Loading**: Enable/disable resources based on environment, feature flags, or user permissions
- **Dependency Injection**: Full access to Symfony services in your provider
- **Backward Compatible**: Works alongside static YAML configuration
- **Recommended Pattern**: Inject resource instances for better performance and no public service requirements
- **Flexible**: Can return resource instances (recommended) or class names (requires public services)

**When to Use Each Approach:**

- Use **YAML configuration** for resources that are always available and don't require conditional logic
- Use **ResourceProviders** when you need:
  - Database-driven resource configuration
  - Conditional resource loading based on environment or feature flags
  - Dynamic resource discovery from external sources
  - Complex initialization logic before registering resources

Both approaches can be used together - resources from YAML configuration and ResourceProviders are merged into a single registry.

## Understanding the Resource Interfaces

### ResourceInterface

All standard resources must implement the `ResourceInterface`, which extends `ResourceDescriptorInterface` and requires six methods:

#### Methods from ResourceDescriptorInterface:

1. **getName(): string**
   - Returns a human-readable name for the resource
   - Best practice: Use a descriptive filename-like format

2. **getDescription(): string**
   - Provides a human-readable description of what the resource contains
   - This helps LLM clients understand the purpose and content of the resource

3. **getMimeType(): string**
   - Specifies the MIME type of the resource (e.g., "text/plain", "application/json")
   - This helps clients properly interpret the resource data

#### Additional Methods in ResourceInterface:

4. **getUri(): string**
   - Returns a unique URI that identifies the resource
   - Best practice: Use a scheme like "file://" followed by a path

5. **getData(): string**
   - Returns the actual content of the resource as a string
   - This is what will be sent to the LLM client when the resource is requested

6. **getSize(): int**
   - Returns the size of the resource data in bytes
   - Typically implemented as `return strlen($this->getData());`

### ResourceTemplateInterface

Resource templates must implement the `ResourceTemplateInterface`, which extends `ResourceDescriptorInterface` and requires five methods:

#### Methods from ResourceDescriptorInterface:

1. **getName(): string**
2. **getDescription(): string**
3. **getMimeType(): string**

#### Additional Methods in ResourceTemplateInterface:

4. **getUriTemplate(): string**
   - Returns a template pattern for URIs that this resource template can handle
   - Example: "file://docs/{filename}.md"

5. **getResource(string $uri): ?ResourceInterface**
   - Retrieves a specific resource by its URI
   - Returns a ResourceInterface instance or null if the resource doesn't exist

6. **resourceExists(string $uri): bool**
   - Checks if a resource with the given URI exists
   - Returns true if the resource exists, false otherwise

## SamplingAwareResourceInterface

For resources that need to make LLM sampling requests during data generation, implement the `SamplingAwareResourceInterface`. This interface extends `ResourceInterface` and provides access to a `SamplingClient` for creating nested LLM calls.

### When to Use Sampling

Use sampling in resources that need:
- Dynamic content generation based on project data
- AI-enhanced summaries or analysis
- Content that adapts based on context
- Natural language processing of existing data

### Implementation

```php
use KLP\KlpMcpServer\Services\ResourceService\SamplingAwareResourceInterface;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;

class ProjectAnalysisResource implements SamplingAwareResourceInterface
{
    private ?SamplingClient $samplingClient = null;
    private ?string $cachedData = null;

    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
        $this->cachedData = null; // Clear cache when client changes
    }

    public function getData(): string
    {
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

        if ($this->samplingClient === null || !$this->samplingClient->canSample()) {
            return 'Static fallback content when sampling is not available';
        }

        try {
            $projectInfo = $this->gatherProjectInfo();
            $prompt = "Analyze this project: " . json_encode($projectInfo);
            
            $response = $this->samplingClient->createTextRequest(
                $prompt,
                new ModelPreferences(
                    hints: [['name' => 'claude-3-sonnet']],
                    intelligencePriority: 0.8
                ),
                null,
                2000 // max tokens
            );

            $this->cachedData = $response->getContent()->getText() ?? 'No analysis generated';
            return $this->cachedData;
        } catch (\Exception $e) {
            return 'Error generating analysis: ' . $e->getMessage();
        }
    }

    // ... other required interface methods
}
```

### Best Practices

- Always check if sampling is available with `canSample()`
- Implement caching to avoid repeated expensive LLM calls
- Provide fallback content when sampling fails
- Handle sampling failures gracefully
- Clear cache when sampling client changes

## Using the Resource Class

For simple resources, you can use the provided `Resource` class instead of implementing the interface from scratch:

```php
use KLP\KlpMcpServer\Services\ResourceService\Resource;

// Create a resource instance
$resource = new Resource(
    "file://example.txt",       // URI
    "example.txt",             // Name
    "An example resource",     // Description
    "text/plain",              // MIME type
    "This is example content"  // Data
);
```

The `Resource` class automatically calculates the size based on the data length and provides setter methods for modifying the resource properties.

## Advanced Resource Development

### Creating Dynamic Resources with Resource Templates

Resource templates allow you to create dynamic resources based on URI patterns. Here's an example that serves Markdown files from a directory:

```php
namespace App\MCP\Resources;

use KLP\KlpMcpServer\Services\ResourceService\Resource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceTemplateInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

class DocumentationResourceTemplate implements ResourceTemplateInterface
{
    private string $baseDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->baseDir = $kernel->getProjectDir().'/docs';
    }

    public function getUriTemplate(): string
    {
        return "file://docs/{filename}.md";
    }

    public function getName(): string
    {
        return "documentation.md";
    }

    public function getDescription(): string
    {
        $finder = new Finder;
        $finder->files()->in($this->baseDir)->name('*.md');

        $filenames = [];
        foreach ($finder as $file) {
            $filenames[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }

        return "Documentation resources. Filename can be one of '".implode("', '", $filenames)."'.";
    }

    public function getMimeType(): string
    {
        return "text/markdown";
    }

    public function getResource(string $uri): ?ResourceInterface
    {
        if (!$this->resourceExists($uri)) {
            return null;
        }
        
        $filename = $this->getFilenameFromUri($uri);
        $path = $this->baseDir.'/'.$filename.'.md';

        $data = file_get_contents($path);
        $title = explode("\n", $data)[0] ?? $filename;

        return new Resource(
            $uri,
            "$filename.md",
            $title,
            "text/markdown",
            $data
        );
    }

    public function resourceExists(string $uri): bool
    {
        $filename = $this->getFilenameFromUri($uri);
        if ($filename === null) {
            return false;
        }

        return file_exists($this->baseDir.'/'.$filename.'.md');
    }

    private function getFilenameFromUri(string $uri): ?string
    {
        if (!preg_match('#^file://docs/([^/]+)\.md$#', $uri, $matches)) {
            return null;
        }
        
        return $matches[1];
    }
}
```

### Accessing Services

For resources that need to access Symfony services, use dependency injection:

```php
namespace App\MCP\Resources;

use KLP\KlpMcpServer\Services\ResourceService\Resource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserManualResource implements ResourceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}
    
    public function getUri(): string
    {
        return "file://user-manual.txt";
    }
    
    public function getName(): string
    {
        return "user-manual.txt";
    }
    
    public function getDescription(): string
    {
        return "User manual for the application.";
    }
    
    public function getMimeType(): string
    {
        return "text/plain";
    }
    
    public function getData(): string
    {
        $this->logger->info('Accessing user manual resource');
        
        // You could fetch content from a database
        $manualContent = $this->entityManager->getRepository(Manual::class)
            ->findOneBy(['type' => 'user'])
            ->getContent();
            
        return $manualContent ?? "Default manual content";
    }
    
    public function getSize(): int
    {
        return strlen($this->getData());
    }
}
```

### Creating Resources from Files

You can create resources that serve content from files:

```php
namespace App\MCP\Resources;

use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ReadmeResource implements ResourceInterface
{
    private string $filePath;
    
    public function __construct(KernelInterface $kernel)
    {
        $this->filePath = $kernel->getProjectDir().'/README.md';
    }
    
    public function getUri(): string
    {
        return "file://readme.md";
    }
    
    public function getName(): string
    {
        return "readme.md";
    }
    
    public function getDescription(): string
    {
        return "Project README file.";
    }
    
    public function getMimeType(): string
    {
        return "text/markdown";
    }
    
    public function getData(): string
    {
        return file_exists($this->filePath) 
            ? file_get_contents($this->filePath) 
            : "README file not found.";
    }
    
    public function getSize(): int
    {
        return strlen($this->getData());
    }
}
```

## Best Practices for MCP Resource Development

### 1. Use Appropriate URI Schemes

URIs should follow a consistent pattern:

- Use `https://` to represent a resource available on the web.
- Use `file://` to identify resources that behave like a filesystem. However, the resources do not need to map to an actual physical filesystem.
- Use `Git://` for Git version control integration.
- Consider using other schemes for different types of resources (e.g., `data:` for embedded data)
- Ensure URIs are unique across all resources

### 2. Provide Clear Metadata

- Use descriptive names and detailed descriptions
- Specify accurate MIME types to help clients interpret the data correctly

### 3. Optimize Resource Size

- Consider chunking very large resources into smaller pieces
- For dynamic resources, implement caching when appropriate
- Be mindful of memory usage when generating large resources

### 4. Handle Errors Gracefully

For resources that fetch data from external sources:

```php
public function getData(): string
{
    try {
        $data = $this->externalService->fetchData();
        return $data;
    } catch (\Exception $e) {
        $this->logger->error('Failed to fetch resource data', [
            'resource' => $this->getUri(),
            'error' => $e->getMessage()
        ]);
        
        return "Error: Unable to retrieve resource data. Please try again later.";
    }
}
```

### 5. Use Resource Templates for Collections

When you have multiple similar resources, use a resource template instead of registering each resource individually:

```php
namespace App\MCP\Resources;

use KLP\KlpMcpServer\Services\ResourceService\Resource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceTemplateInterface;

class ApiDocumentationTemplate implements ResourceTemplateInterface
{
    private array $endpoints = [
        'users' => 'User management API endpoints',
        'products' => 'Product catalog API endpoints',
        'orders' => 'Order processing API endpoints',
    ];
    
    public function getUriTemplate(): string
    {
        return "file://api-docs/{endpoint}.json";
    }
    
    public function getName(): string
    {
        return "api-documentation.json";
    }
    
    public function getDescription(): string
    {
        return "API documentation for endpoints: " . implode(', ', array_keys($this->endpoints));
    }
    
    public function getMimeType(): string
    {
        return "application/json";
    }
    
    public function getResource(string $uri): ?ResourceInterface
    {
        $endpoint = $this->getEndpointFromUri($uri);
        if (!$endpoint || !isset($this->endpoints[$endpoint])) {
            return null;
        }
        
        $data = $this->generateDocumentation($endpoint);
        
        return new Resource(
            $uri,
            "$endpoint-api.json",
            $this->endpoints[$endpoint],
            "application/json",
            $data
        );
    }
    
    public function resourceExists(string $uri): bool
    {
        $endpoint = $this->getEndpointFromUri($uri);
        return $endpoint !== null && isset($this->endpoints[$endpoint]);
    }
    
    private function getEndpointFromUri(string $uri): ?string
    {
        if (!preg_match('#^file://api-docs/([^/]+)\.json$#', $uri, $matches)) {
            return null;
        }
        
        return $matches[1];
    }
    
    private function generateDocumentation(string $endpoint): string
    {
        // In a real implementation, this would generate or fetch the actual documentation
        return json_encode([
            'endpoint' => $endpoint,
            'description' => $this->endpoints[$endpoint],
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'parameters' => [
                // Example parameters
            ]
        ], JSON_PRETTY_PRINT);
    }
}
```

## Example Resources

### Example 1: Simple Text Resource

```php
namespace App\MCP\Resources;

use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;

class WelcomeResource implements ResourceInterface
{
    public function getUri(): string
    {
        return "file://welcome.txt";
    }

    public function getName(): string
    {
        return "welcome.txt";
    }

    public function getDescription(): string
    {
        return "Welcome message for new users.";
    }

    public function getMimeType(): string
    {
        return "text/plain";
    }

    public function getData(): string
    {
        return "Welcome to our application! We're excited to have you on board.";
    }

    public function getSize(): int
    {
        return strlen($this->getData());
    }
}
```

### Example 2: JSON Configuration Resource

```php
namespace App\MCP\Resources;

use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;

class AppConfigResource implements ResourceInterface
{
    public function getUri(): string
    {
        return "file://app-config.json";
    }

    public function getName(): string
    {
        return "app-config.json";
    }

    public function getDescription(): string
    {
        return "Application configuration settings.";
    }

    public function getMimeType(): string
    {
        return "application/json";
    }

    public function getData(): string
    {
        $config = [
            'appName' => 'My MCP Application',
            'version' => '1.0.0',
            'features' => [
                'darkMode' => true,
                'notifications' => true,
                'analytics' => false
            ],
            'limits' => [
                'maxUploadSize' => '10MB',
                'requestsPerMinute' => 60
            ]
        ];
        
        return json_encode($config, JSON_PRETTY_PRINT);
    }

    public function getSize(): int
    {
        return strlen($this->getData());
    }
}
```

## Conclusion

MCP Resources provide a powerful way to share data with LLM clients, enabling them to access information that enhances their capabilities. By following this guide, you can create well-designed resources that effectively expose your application's data to LLM clients through the Model Context Protocol.

For more information about the Model Context Protocol, visit the [official MCP documentation](https://modelcontextprotocol.io/) or explore the [MCP specification](https://github.com/modelcontextprotocol/specification).
