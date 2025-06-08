# Building MCP Tools - Complete Walkthrough

## Introduction to Model Context Protocol (MCP)

The Model Context Protocol (MCP) is a standardized communication protocol that enables Large Language Models (LLMs) to interact with external systems and services. MCP allows LLMs to:

- Execute functions in your application
- Access real-time data
- Perform complex operations beyond their training data
- Interact with your business logic in a controlled manner

This Symfony MCP Server implementation uses Server-Sent Events (SSE) transport for secure, real-time communication between LLM clients and your application.

## What are MCP Tools?

MCP Tools are the building blocks that expose your application's functionality to LLM clients. Each tool:

- Has a unique name and description
- Defines a specific input schema using JSON Schema
- Implements execution logic that processes inputs and returns results
- Can be discovered and invoked by LLM clients

## Creating Your First MCP Tool

### Step 1: Generate a Tool Using the Command Line

The easiest way to create a new tool is using the provided command:

```bash
php bin/console make:mcp-tool MyCustomTool
```

This command:
- Creates a properly structured tool class in `src/MCP/Tools`
- Automatically formats the name (adding "Tool" suffix if needed)
- Generates a kebab-case tool name for the `getName()` method
- Offers to register the tool in your configuration

### Step 2: Understanding the Generated Tool Structure

The generated tool implements the `StreamableToolInterface` and includes these key methods:

```php
// src/MCP/Tools/MyCustomTool.php
namespace App\MCP\Tools;

use ableKLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\StreamToolInterface;

class MyCustomTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'my-custom'; // Kebab-case name
    }

    public function getDescription(): string
    {
        return 'Description of MyCustomTool';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'First parameter description',
                ],
                // Add more parameters as needed
            ],
            'required' => ['param1'],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation;
    }

    public function execute(array $arguments): string
    {
        $param1 = $arguments['param1'] ?? 'default';

        // Implement your tool logic here
        return "Tool executed with parameter: {$param1}";
    }
}
```

### Step 3: Registering Your Tool

If you chose not to automatically register your tool during creation, you need to add it to the configuration file:

```yaml
# config/packages/klp_mcp_server.yaml
klp_mcp_server:
    # ... other configuration
    tools:
        - App\MCP\Tools\MyCustomTool
        # Add other tools here
```

## Understanding the Tool Interface

All MCP tools must implement the `StreamableToolInterface`, which requires five methods:

### 1. getName(): string

Returns a unique identifier for your tool. Best practices:
- Use kebab-case (e.g., `my-custom-tool`)
- Keep it short but descriptive
- Avoid spaces and special characters

### 2. getDescription(): string

Provides a human-readable description of what your tool does. This helps LLM clients understand when to use your tool.

### 3. getInputSchema(): array

Defines the expected input format using JSON Schema. This is crucial for:
- Input validation
- Providing type hints to LLM clients
- Documenting required vs. optional parameters

Example schema with multiple parameters:

```php
public function getInputSchema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'username' => [
                'type' => 'string',
                'description' => 'The username to look up',
            ],
            'includeDetails' => [
                'type' => 'boolean',
                'description' => 'Whether to include detailed information',
                'default' => false,
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of results',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 10,
            ],
        ],
        'required' => ['username'],
    ];
}
```

### 4. getAnnotations(): ToolAnnotation

According to the official specification, tool annotations provide additional metadata about a tool’s behavior, helping clients understand how to present and manage tools. These annotations are hints that describe the nature and impact of a tool, but should not be relied upon for security decisions.

Here:

```php
public function getAnnotations(): ToolAnnotation
{
    return new ToolAnnotation(
        title: '-',             # string  default '-'   A human-readable title for the tool, useful for UI display
        readOnlyHint: false,    # boolean default false If true, indicates the tool does not modify its environment
        destructiveHint: true,  # boolean default true  If true, the tool may perform destructive updates (only meaningful when readOnlyHint is false)
        idemptentHint: false,   # boolean default false If true, calling the tool repeatedly with the same arguments has no additional effect (only meaningful when readOnlyHint is false)
        openWorldHint: true     # boolean default true  If true, the tool may interact with an “open world” of external entities
    );
}
```

### 5. execute(array $arguments): mixed

Contains the actual logic of your tool. This method:
- Receives validated input arguments
- Performs the tool's functionality
- Returns results (typically as a string, but can be any serializable data)

Best practices:
- Validate inputs even though the framework does basic validation
- Handle exceptions gracefully
- Return clear, structured responses
- Keep execution time reasonable

## Testing Your MCP Tools

### Using the Test Command

The package includes a dedicated command for testing your tools:

```bash
# Test a specific tool interactively
php bin/console mcp:test-tool MyCustomTool

# List all available tools
php bin/console mcp:test-tool --list

# Test with specific JSON input
php bin/console mcp:test-tool MyCustomTool --input='{"param1":"value"}'
```

This command helps you:
- Verify your tool's input schema
- Test execution with different inputs
- Debug issues before deployment

### Using the MCP Inspector

For visual testing, you can use the Model Context Protocol Inspector:

```bash
# Run the MCP Inspector without installation
npx @modelcontextprotocol/inspector node build/index.js
```

This opens a web interface (typically at `localhost:6274`) where you can:
1. Connect to your MCP server
2. Browse available tools
3. Test tools with different inputs
4. View formatted results

**Note:** Your Symfony application must be running with a proper web server (not `symfony server:start`), as MCP SSE requires processing multiple connections concurrently.

## Advanced Tool Development

### Handling Complex Inputs

Your tools can accept complex nested objects and arrays:

```php
public function getInputSchema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'user' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                    'roles' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['name'],
            ],
            'options' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['user'],
    ];
}
```

### Accessing Services

For tools that need to access Symfony services, use dependency injection:

```php
class DatabaseQueryTool implements StreamableToolInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}
    
    // ... other methods
    
    public function execute(array $arguments): string
    {
        $this->logger->info('Executing database query tool');
        
        // Use $this->entityManager to query the database
        $result = $this->entityManager->getRepository(User::class)
            ->findBy(['username' => $arguments['username']]);
            
        // Process and return results
        return json_encode($result);
    }
}
```

### Returning Different Data Types

While tools often return strings, you can return any JSON encodable data:

```php
public function execute(array $arguments): array
{
    // Return a structured array
    return [
        'status' => 'success',
        'data' => [
            'id' => 123,
            'name' => $arguments['name'],
            'created' => new \DateTime(),
        ],
    ];
}
```

### Error Handling

Proper error handling improves the user experience:

```php
public function execute(array $arguments): string
{
    try {
        // Attempt to perform operation
        $result = $this->someService->performOperation($arguments);
        return "Operation successful: {$result}";
    } catch (NotFoundException $e) {
        // Handle specific exceptions with informative messages
        return "Resource not found: {$e->getMessage()}";
    } catch (\Exception $e) {
        // Log unexpected errors but return a user-friendly message
        $this->logger->error('Tool execution failed', [
            'tool' => $this->getName(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return "Operation failed: An unexpected error occurred";
    }
}
```

## Best Practices for MCP Tool Development

### 1. Keep Tools Focused

Each tool should do one thing well. Instead of creating a single complex tool, consider splitting functionality into multiple specialized tools.

### 2. Provide Clear Documentation

- Write clear, concise descriptions
- Document each parameter thoroughly

### 3. Validate Inputs Thoroughly

While the framework performs basic validation against your schema, add additional validation for business rules:

```php
public function execute(array $arguments): string
{
    $username = $arguments['username'] ?? '';
    
    // Additional validation beyond schema
    if (strlen($username) < 3) {
        return "Error: Username must be at least 3 characters long";
    }
    
    // Continue with execution...
}
```

### 4. Handle Rate Limiting

For resource-intensive tools, consider implementing rate limiting:

```php
public function execute(array $arguments): string
{
    $clientId = $arguments['_client_id'] ?? 'unknown';
    
    if (!$this->rateLimiter->allowRequest('tool_execution', $clientId)) {
        return "Rate limit exceeded. Please try again later.";
    }
    
    // Continue with execution...
}
```

### 5. Return Structured Responses

Consistent response formats make it easier for LLM clients to parse results:

```php
public function execute(array $arguments): array
{
    // ... tool logic
    
    // Return a consistently structured response
    return [
        'status' => 'success',
        'data' => $result,
        'metadata' => [
            'processingTime' => $processingTime,
            'resultCount' => count($result),
        ],
    ];
}
```

### 6. Test Edge Cases

Use the testing command to verify your tool handles edge cases correctly:
- Missing optional parameters
- Invalid inputs
- Empty results
- Very large inputs/outputs

## Example Tools

### Example 1: Hello World Tool

A simple tool that greets a user:

```php
class HelloWorldTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'hello-world';
    }

    public function getDescription(): string
    {
        return 'Say HelloWorld to a developer.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Developer Name',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation;
    }

    public function execute(array $arguments): string
    {
        $name = $arguments['name'] ?? 'MCP';

        return "Hello, HelloWorld `{$name}` developer.";
    }
}
```

### Example 2: Version Check Tool

A tool that returns the current Symfony version:

```php
final class VersionCheckTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'check-version';
    }

    public function getDescription(): string
    {
        return 'Check the current Symfony version.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new stdClass,
            'required' => [],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation;
    }

    public function execute(array $arguments): string
    {
        $now = (new \DateTime('now'))->format('Y-m-d H:i:s');
        $version = Kernel::VERSION;

        return "current Version: {$version} - {$now}";
    }
}
```

## Conclusion

MCP Tools provide a powerful way to extend the capabilities of LLMs by giving them access to your application's functionality. By following this guide, you can create well-designed, secure, and effective tools that enhance the capabilities of LLM clients interacting with your Symfony application.

For more information about the Model Context Protocol, visit the [official MCP documentation](https://modelcontextprotocol.io/) or explore the [MCP specification](https://github.com/modelcontextprotocol/specification).
