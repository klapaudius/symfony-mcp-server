# Building MCP Tools - Complete Walkthrough

## Table of Contents
1. [Introduction to Model Context Protocol (MCP)](#introduction-to-model-context-protocol-mcp)
2. [What are MCP Tools?](#what-are-mcp-tools)
3. [Creating Your First MCP Tool](#creating-your-first-mcp-tool)
4. [Understanding the Tool Interface](#understanding-the-tool-interface)
5. [Tool Result Types](#tool-result-types)
6. [Testing Your MCP Tools](#testing-your-mcp-tools)
7. [Streaming Tools with Progress Notifications](#streaming-tools-with-progress-notifications)
8. [SamplingAwareToolInterface](#samplingawaretoolinterface)
9. [Advanced Tool Development](#advanced-tool-development)
10. [Best Practices for MCP Tool Development](#best-practices-for-mcp-tool-development)
11. [Example Tools](#example-tools)
12. [Conclusion](#conclusion)

## Introduction to Model Context Protocol (MCP)

The Model Context Protocol (MCP) is a standardized communication protocol that enables Large Language Models (LLMs) to interact with external systems and services. MCP allows LLMs to:

- Execute functions in your application
- Access real-time data
- Perform complex operations beyond their training data
- Interact with your business logic in a controlled manner

This Symfony MCP Server implementation supports multiple transport protocols for secure, real-time communication between LLM clients and your application, including Server-Sent Events (SSE) and StreamableHTTP Protocol.

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

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

class MyCustomTool implements StreamableToolInterface
{
    private ?ProgressNotifier $progressNotifier = null;

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

    public function isStreaming(): bool
    {
        return false;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        $this->progressNotifier = $progressNotifier;
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

All MCP tools must implement the `StreamableToolInterface`, which requires methods:

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

### 5. execute(array $arguments): ToolResultInterface

Contains the actual logic of your tool. This method:
- Receives validated input arguments
- Performs the tool's functionality
- Returns results

**Best practices:**
- Validate inputs even though the framework does basic validation
- Handle exceptions gracefully
- Return clear, structured responses
- Keep execution time reasonable

### 6. isStreaming(): bool

Determines whether the tool supports streaming responses with real-time progress updates. This method is crucial for enabling the MCP server to provide live feedback during long-running operations.

**When to return `true`:**
- Tools that perform batch processing operations
- File processing with multiple items
- Data analysis or computation that takes significant time
- API calls with multiple steps
- Any operation where progress feedback would be valuable

**When to return `false`:**
- Simple, fast operations (< 1 second)
- Tools that don't benefit from progress tracking
- Operations that can't be meaningfully broken into progress steps

**Important Notes:**
- Only tools returning `true` will receive progress notifier instances
- Progress notifications are only sent when `isStreaming()` returns `true`

### 7. setProgressNotifier(ProgressNotifierInterface $progressNotifier): void

Injects a progress notifier instance that allows the tool to send real-time progress updates to the client during execution. This method is called automatically by the framework for streaming tools.

**Using the Progress Notifier:**

```php
public function execute(array $arguments): ToolResultInterface
{
    $totalItems = count($arguments['items']);
    $processedItems = 0;
    
    foreach ($arguments['items'] as $item) {
        // Process the item
        $result = $this->processItem($item);
        $processedItems++;
        
        // Send progress notification
        if ($this->progressNotifier && $this->isStreaming()) {
            try {
                $this->progressNotifier->sendProgress(
                    progress: $processedItems,
                    total: $totalItems,
                    message: "Processed {$processedItems}/{$totalItems} items"
                );
            } catch (\Exception $e) {
                // Continue processing even if progress notification fails
                error_log("Progress notification failed: " . $e->getMessage());
            }
        }
    }
    
    return new TextToolResult("Processing complete");
}
```

**Progress Notification Parameters:**
- `progress` (required): Current progress value (must always increase)
- `total` (optional): Total expected value for percentage calculation
- `message` (optional): Human-readable progress description

**Best Practices:**
- Always check if the notifier exists and streaming is enabled
- Wrap progress notifications in try-catch blocks
- Continue processing even if notifications fail
- Provide meaningful progress messages
- Ensure progress values always increase (framework validates this)
- Use appropriate granularity for progress updates (not too frequent, not too sparse)

**Progress Notification Flow:**
1. Client sends tool call request with progress token in `_meta.progressToken`
2. Framework registers the progress token and creates a notifier
3. Tool receives the notifier via `setProgressNotifier()`
4. During execution, tool calls `sendProgress()` to update progress
5. Framework sends real-time notifications to client via SSE/HTTP streaming
6. Client receives progress updates and can display them to the user

### Tool Result Types

Streaming tools must return objects that implement `ToolResultInterface`. The framework provides several built-in result types:

#### TextToolResult
For plain text responses:

```php
return new TextToolResult("Operation completed successfully");
```

#### ImageToolResult
For image data:

```php
return new ImageToolResult($imageData, 'image/png');
```

#### AudioToolResult
For audio data:

```php
return new AudioToolResult($audioData, 'audio/wav');
```

#### ResourceToolResult
For referencing external resources:

```php
return new ResourceToolResult($resourceUri, 'application/json');
```

#### CollectionToolResult
For combining multiple tool results into a single response:

```php
$collection = new CollectionToolResult;
$collection->addItem(new TextToolResult("Operation completed successfully"));
$collection->addItem(new ImageToolResult($imageData, 'image/png'));

return $collection;
```

You can add any type of ToolResultInterface object to a CollectionToolResult, except another CollectionToolResult (nesting is not supported).

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
1. Connect to your MCP server (using either SSE or StreamableHTTP Protocol)
2. Browse available tools
3. Test tools with different inputs
4. View formatted results

**Note:** Your Symfony application must be running with a proper web server (not `symfony server:start`), as MCP transport protocols require processing multiple connections concurrently.

## Streaming Tools with Progress Notifications

### Overview of Streaming Tools

The MCP Server supports streaming tools that can provide real-time progress updates during long-running operations. This is particularly useful for:

- Data processing operations
- API calls with multiple steps
- Any operation that takes significant time to complete

### Creating a Streaming Tool

Streaming tools implement the `StreamableToolInterface` and return `ToolResultInterface` objects instead of simple strings. They also support progress notifications through the `ProgressNotifierInterface`.

Here's a complete example of a streaming tool:

```php
<?php

namespace App\MCP\Tools;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

class DataProcessingTool implements StreamableToolInterface
{
    private ?ProgressNotifierInterface $progressNotifier = null;

    public function getName(): string
    {
        return 'process-data';
    }

    public function getDescription(): string
    {
        return 'Processes data with real-time progress updates';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dataset' => [
                    'type' => 'string',
                    'description' => 'The dataset to process',
                ],
                'batchSize' => [
                    'type' => 'integer',
                    'description' => 'Number of items to process per batch',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 10,
                ],
            ],
            'required' => ['dataset'],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation(
            readOnlyHint: false,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $dataset = $arguments['dataset'];
        $batchSize = $arguments['batchSize'] ?? 10;
        $totalItems = strlen($dataset); // Simple example
        $processedItems = 0;
        $results = [];

        // Process data in batches
        for ($i = 0; $i < $totalItems; $i += $batchSize) {
            $batch = substr($dataset, $i, $batchSize);
            
            // Simulate processing work
            usleep(500000); // 500ms delay
            
            // Process the batch
            $batchResult = $this->processBatch($batch);
            $results[] = $batchResult;
            
            $processedItems += strlen($batch);

            // Send progress notification if streaming
            if ($this->progressNotifier && $this->isStreaming()) {
                try {
                    $this->progressNotifier->sendProgress(
                        progress: $processedItems,
                        total: $totalItems,
                        message: "Processed {$processedItems}/{$totalItems} items"
                    );
                } catch (\Exception $e) {
                    // Continue processing even if progress notification fails
                    error_log("Progress notification failed: " . $e->getMessage());
                }
            }
        }

        return new TextToolResult(
            "Processing complete. Processed " . count($results) . " batches.\n" .
            "Results: " . implode(', ', $results)
        );
    }

    public function isStreaming(): bool
    {
        return true;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        $this->progressNotifier = $progressNotifier;
    }

    private function processBatch(string $batch): string
    {
        // Your actual processing logic here
        return "Processed: " . $batch;
    }
}
```

### Progress Notifications

Progress notifications allow clients to track the progress of long-running operations. The `ProgressNotifierInterface` provides a simple API:

```php
// Basic progress notification
$this->progressNotifier->sendProgress(
    progress: 50,    // Current progress value
    total: 100,      // Total expected value (optional)
    message: "Processing item 50 of 100"  // Human-readable message (optional)
);
```

**Important Notes:**
- Progress values must always increase with each notification
- The framework will throw a `ProgressTokenException` if progress decreases
- Continue processing even if progress notifications fail
- Progress notifications are only sent when `isStreaming()` returns `true`

### Streaming Tool Best Practices

#### 1. Handle Progress Notification Failures Gracefully

```php
if ($this->progressNotifier && $this->isStreaming()) {
    try {
        $this->progressNotifier->sendProgress($current, $total, $message);
    } catch (\Exception $e) {
        // Log the error but don't stop processing
        error_log("Progress notification failed: " . $e->getMessage());
    }
}
```

#### 2. Provide Meaningful Progress Messages

```php
$this->progressNotifier->sendProgress(
    progress: $processedFiles,
    total: $totalFiles,
    message: "Processing file: {$currentFileName} ({$processedFiles}/{$totalFiles})"
);
```

#### 3. Use Appropriate Batch Sizes

Choose batch sizes that balance performance with progress update frequency:

```php
// For large datasets, use reasonable batch sizes
$batchSize = min(100, max(1, intval($totalItems / 20))); // 20 progress updates max
```

#### 4. Implement Proper Error Handling

```php
public function execute(array $arguments): ToolResultInterface
{
    try {
        // ... processing logic
        
        return new TextToolResult("Success: Processed {$count} items");
    } catch (\Exception $e) {
        // Log the error
        error_log("Tool execution failed: " . $e->getMessage());
        
        // Return an error result
        return new TextToolResult("Error: " . $e->getMessage());
    }
}
```

### Testing Streaming Tools

When testing streaming tools, you can use the MCP test command:

```bash
# Test with progress notifications
php bin/console mcp:test-tool DataProcessingTool --input='{"dataset":"test data","batchSize":5}'
```

The test command will show progress notifications in real-time during execution.

### Example: File Processing Tool

Here's a complete example of a streaming tool that processes multiple files:

```php
class FileProcessingTool implements StreamableToolInterface
{
    private ?ProgressNotifierInterface $progressNotifier = null;

    public function getName(): string
    {
        return 'process-files';
    }

    public function getDescription(): string
    {
        return 'Process multiple files with progress tracking';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'filePaths' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array of file paths to process',
                ],
                'operation' => [
                    'type' => 'string',
                    'enum' => ['validate', 'convert', 'analyze'],
                    'description' => 'Operation to perform on files',
                ],
            ],
            'required' => ['filePaths', 'operation'],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation(
            readOnlyHint: false,
            destructiveHint: true,
            idempotentHint: false,
            openWorldHint: true
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $filePaths = $arguments['filePaths'];
        $operation = $arguments['operation'];
        $totalFiles = count($filePaths);
        $processedFiles = 0;
        $results = [];

        foreach ($filePaths as $filePath) {
            try {
                // Process individual file
                $result = $this->processFile($filePath, $operation);
                $results[] = $result;
                $processedFiles++;

                // Send progress update
                if ($this->progressNotifier && $this->isStreaming()) {
                    try {
                        $this->progressNotifier->sendProgress(
                            progress: $processedFiles,
                            total: $totalFiles,
                            message: "Processed: {$filePath} ({$processedFiles}/{$totalFiles})"
                        );
                    } catch (\Exception $e) {
                        error_log("Progress notification failed: " . $e->getMessage());
                    }
                }

                // Small delay to simulate processing time
                usleep(100000); // 100ms
                
            } catch (\Exception $e) {
                $results[] = "Error processing {$filePath}: " . $e->getMessage();
            }
        }

        return new TextToolResult(
            "File processing complete.\n" .
            "Operation: {$operation}\n" .
            "Files processed: {$processedFiles}/{$totalFiles}\n" .
            "Results:\n" . implode("\n", $results)
        );
    }

    public function isStreaming(): bool
    {
        return true;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        $this->progressNotifier = $progressNotifier;
    }

    private function processFile(string $filePath, string $operation): string
    {
        // Your file processing logic here
        switch ($operation) {
            case 'validate':
                return "✓ {$filePath} is valid";
            case 'convert':
                return "✓ {$filePath} converted successfully";
            case 'analyze':
                return "✓ {$filePath} analyzed: " . filesize($filePath) . " bytes";
            default:
                throw new \InvalidArgumentException("Unknown operation: {$operation}");
        }
    }
}
```

## SamplingAwareToolInterface

For tools that need to make LLM sampling requests during execution, implement the `SamplingAwareToolInterface`. This interface extends `StreamableToolInterface` and provides access to a `SamplingClient` for creating nested LLM calls.

### When to Use Sampling

Use sampling in tools that need:
- Code analysis and recommendations
- Content generation or transformation
- Complex reasoning tasks
- Natural language processing

### Implementation

```php
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\ToolService\SamplingAwareToolInterface;

class MyAnalysisTool implements SamplingAwareToolInterface
{
    private ?SamplingClient $samplingClient = null;

    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }

    public function execute(array $arguments): ToolResultInterface
    {
        if ($this->samplingClient === null || !$this->samplingClient->canSample()) {
            return new TextToolResult('This tool requires LLM sampling capability');
        }

        $prompt = "Analyze this data: " . $arguments['data'];
        
        $response = $this->samplingClient->createTextRequest(
            $prompt,
            new ModelPreferences(
                hints: [['name' => 'claude-3-sonnet']],
                intelligencePriority: 0.8
            ),
            null,
            2000 // max tokens
        );

        return new TextToolResult($response->getContent()->getText() ?? 'No response');
    }
}
```

### Best Practices

- Always check if sampling is available with `canSample()`
- Handle sampling failures gracefully
- Use appropriate model preferences for your use case
- Set reasonable token limits to control costs

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

MCP Tools provide a powerful way to extend the capabilities of LLMs by giving them access to your application's functionality. By following this guide, you can create well-designed, secure, and effective tools that enhance the capabilities of LLM clients interacting with your Symfony application. With support for both SSE and StreamableHTTP Protocol, you can choose the transport method that best fits your application's needs.

For more information about the Model Context Protocol, visit the [official MCP documentation](https://modelcontextprotocol.io/) or explore the [MCP specification](https://github.com/modelcontextprotocol/specification).
