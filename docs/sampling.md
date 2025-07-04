# Sampling Feature

The Symfony MCP Server bundle now supports the Model Context Protocol (MCP) sampling feature, which allows MCP servers (tools, prompts, and resources) to request LLM assistance from clients during execution.

## Overview

Sampling enables tools to make nested LLM calls for complex reasoning, content generation, or natural language understanding tasks. This creates a bidirectional communication channel where both client and server can contribute intelligence to interactions.

## How It Works

1. **Client Capability Declaration**: During initialization, clients declare their sampling capability
2. **MCP Component Execution**: When a tool, prompt, or resource needs LLM assistance, it can make a sampling request
3. **Client Processing**: The client receives the request and forwards it to an LLM
4. **Response**: The LLM's response is sent back to the requesting component

## Implementation

### For Tool Developers

To create a tool that uses sampling:

1. Implement the `SamplingAwareToolInterface`:

```php
use KLP\KlpMcpServer\Services\ToolService\SamplingAwareToolInterface;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

class MyTool implements SamplingAwareToolInterface
{
    private SamplingClient|null $samplingClient = null;
    
    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }
    
    public function execute(array $arguments): ToolResultInterface
    {
        if ($this->samplingClient === null || !$this->samplingClient->canSample()) {
            return new TextToolResult('This tool requires sampling capability');
        }
        
        // Make a sampling request
        $response = $this->samplingClient->createTextRequest(
            'Analyze this data: ' . $arguments['data'],
            new ModelPreferences(
                hints: [['name' => 'claude-3-sonnet']],
                intelligencePriority: 0.8
            )
        );
        
        return new TextToolResult($response->getContent()->getText());
    }
    
    // ... other required methods
}
```

2. The framework automatically injects the `SamplingClient` when the tool is executed.

### For Prompt Developers

Prompts can also use sampling to generate dynamic content based on user input:

```php
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\SamplingAwarePromptInterface;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

class DynamicPrompt implements PromptInterface, SamplingAwarePromptInterface
{
    private ?SamplingClient $samplingClient = null;
    
    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }
    
    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $content = $arguments['content'] ?? '';
        
        $collection = new CollectionPromptMessage([
            new TextPromptMessage('system', 'You are an expert reviewer.')
        ]);

        // Generate dynamic questions if sampling is available
        if ($this->samplingClient !== null && $this->samplingClient->canSample()) {
            $dynamicQuestions = $this->generateQuestions($content);
            if ($dynamicQuestions) {
                $collection->addMessage(
                    new TextPromptMessage('user', $dynamicQuestions)
                );
            }
        }

        return $collection;
    }
    
    private function generateQuestions(string $content): ?string
    {
        try {
            $response = $this->samplingClient->createTextRequest(
                "Generate specific review questions for: " . $content,
                new ModelPreferences(speedPriority: 0.8),
                null,
                300
            );
            return $response->getContent()->getText();
        } catch (\Exception $e) {
            return null; // Fallback gracefully
        }
    }
}
```

### For Resource Developers

Resources can use sampling to generate dynamic content based on project data:

```php
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\SamplingAwareResourceInterface;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

class ProjectSummaryResource implements ResourceInterface, SamplingAwareResourceInterface
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
            return $this->getStaticSummary();
        }

        try {
            $projectInfo = $this->gatherProjectInfo();
            $response = $this->samplingClient->createTextRequest(
                "Generate a project summary: " . json_encode($projectInfo),
                new ModelPreferences(intelligencePriority: 0.8),
                null,
                2000
            );

            $this->cachedData = $response->getContent()->getText() ?? 'No summary generated';
            return $this->cachedData;
        } catch (\Exception $e) {
            return $this->getStaticSummary() . "\n\n*Note: Dynamic summary generation failed.*";
        }
    }
    
    // ... other required interface methods
}
```

### Sampling Request Types

#### Text Sampling
```php
$response = $this->samplingClient->createTextRequest(
    'Your prompt here',
    $modelPreferences,  // Optional
    $systemPrompt,      // Optional
    $maxTokens          // Optional
);
```

#### Multi-Message Sampling
```php
$messages = [
    new SamplingMessage('user', new SamplingContent('text', 'First message')),
    new SamplingMessage('assistant', new SamplingContent('text', 'Response')),
    new SamplingMessage('user', new SamplingContent('text', 'Follow-up')),
];

$response = $this->samplingClient->createRequest(
    $messages,
    $modelPreferences,
    $systemPrompt,
    $maxTokens
);
```

### Model Preferences

You can specify preferences for model selection:

```php
$modelPreferences = new ModelPreferences(
    hints: [
        ['name' => 'claude-3-sonnet'],
        ['name' => 'claude-3-opus']
    ],
    costPriority: 0.3,         // 0-1, higher = prefer cheaper
    speedPriority: 0.5,        // 0-1, higher = prefer faster
    intelligencePriority: 0.8  // 0-1, higher = prefer smarter
);
```

## Example: Code Analyzer Tool

See `src/Services/ToolService/Example/CodeAnalyzerTool.php` for a complete example of a tool that uses sampling to analyze code for security, performance, or readability issues.

## Configuration

The sampling feature is automatically enabled when:
1. The client declares sampling capability during initialization
2. Components implement the appropriate sampling-aware interface:
   - Tools: `SamplingAwareToolInterface`
   - Prompts: `SamplingAwarePromptInterface`
   - Resources: `SamplingAwareResourceInterface`

No additional configuration is required.

## Security Considerations

- Sampling requests should be used judiciously to avoid overwhelming clients
- Clients typically implement human-in-the-loop approval for sampling requests
- Always validate and sanitize data before including it in sampling requests
- Be mindful of sensitive information that might be sent to LLMs

## Current Limitations

- The current implementation requires asynchronous message handling for proper response processing
- Sampling is only available for clients that explicitly support it
- Response handling is simplified and may need enhancement for production use

## Best Practices

1. **Check Capability**: Always verify sampling is available before using it
2. **Provide Context**: Include relevant context in your prompts for better results
3. **Handle Errors**: Gracefully handle cases where sampling fails or is unavailable
4. **Model Hints**: Provide appropriate model hints based on your task requirements
5. **Token Limits**: Set reasonable token limits to control response length and cost
