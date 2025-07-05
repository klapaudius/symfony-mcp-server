<h1 align="center">Symfony MCP Server</h1>

<p align="center">
  <strong>Build Intelligent AI Agents with Symfony</strong><br>
  Transform your Symfony applications into powerful AI-driven systems
</p>

<p align="center">
<a href="https://github.com/klapaudius/symfony-mcp-server/actions"><img src="https://github.com/klapaudius/symfony-mcp-server/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/klapaudius/symfony-mcp-server" >  <img src="https://codecov.io/gh/klapaudius/symfony-mcp-server/graph/badge.svg?token=5FXOJVXPZ1" alt="Coverage"/></a>
<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/l/klapaudius/symfony-mcp-server" alt="License"></a>
<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/v/klapaudius/symfony-mcp-server" alt="Latest Stable Version"></a>

[//]: # (<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/dt/klapaudius/symfony-mcp-server" alt="Total Downloads"></a>)
</p>

## 🤖 Unleash the Power of AI Agents in Your Symfony Apps

Symfony MCP Server enables you to build **intelligent, context-aware AI agents** that can reason, make decisions, and interact with your application's business logic. By implementing the Model Context Protocol (MCP), your Symfony application becomes a platform for sophisticated AI-driven automation and intelligence.

### 🎯 Why Build Agents with Symfony MCP Server?

**Transform Static Tools into Intelligent Agents:**
- 🧠 **AI-Powered Reasoning**: Tools can consult LLMs mid-execution to make smart decisions
- 🔄 **Dynamic Adaptation**: Agents adapt their behavior based on context and real-time analysis
- 💡 **Complex Problem Solving**: Break down complex tasks and solve them iteratively with AI assistance
- 🎨 **Creative Generation**: Generate content and solutions that evolve with user needs

**Enterprise-Grade Security:**
- 🔒 **Secure Transports**: StreamableHTTP and SSE instead of STDIO for production environments
- 🛡️ **Protected APIs**: Keep your internal systems safe while exposing AI capabilities
- 🎛️ **Fine-Grained Control**: Manage authentication, authorization, and access at every level

## 🚀 Agent-First Features

### 🧪 Sampling: The Core of Agentic Behavior (v1.4.0+)

Transform your tools into autonomous agents that can think and reason:

```php
class IntelligentAnalyzer implements SamplingAwareToolInterface
{
    public function execute(array $arguments): ToolResultInterface
    {
        // Let AI analyze and reason about complex data
        $analysis = $this->sampling->createRequest([
            'role' => 'user',
            'content' => "Analyze this data and suggest optimizations: {$arguments['data']}"
        ]);
        
        $aiResponse = $this->sampling->sendRequest($analysis);
        
        // Execute actions based on AI reasoning
        return $this->processAIRecommendations($aiResponse);
    }
}
```

### 🛠️ Tool System: Building Blocks for Agents

Create powerful tools that AI agents can orchestrate:
- **StreamableToolInterface**: Real-time progress updates for long-running operations
- **Multi-Result Support**: Return text, images, audio, or resources
- **Progress Notifications**: Keep users informed during complex agent operations
- **Dynamic Tool Discovery**: Agents can discover and use tools based on capabilities

### 🎭 Prompt Engineering for Agent Behavior

Define agent personalities and behaviors through sophisticated prompt systems:
- **Context-Aware Prompts**: Guide agent behavior based on application state
- **Multi-Modal Support**: Text, image, audio, and resource-based prompts
- **Dynamic Prompt Generation**: Prompts that adapt based on user interaction

### 📚 Resource Management for Agent Memory

Give your agents access to structured knowledge:
- **Dynamic Resource Loading**: Agents can access and reason about your data
- **Template-Based Resources**: Generate resources on-the-fly based on context
- **Multi-Provider Support**: File system, database, API, or custom providers

## 🎯 Real-World Agent Examples

### 🔍 Intelligent Code Review Agent
```php
class CodeReviewAgent implements SamplingAwareToolInterface
{
    public function execute(array $arguments): ToolResultInterface
    {
        // AI analyzes code for patterns, security, and best practices
        $review = $this->sampling->createRequest([
            'role' => 'user',
            'content' => "Review this code for security vulnerabilities, 
                         performance issues, and suggest improvements: 
                         {$arguments['code']}"
        ]);
        
        $aiAnalysis = $this->sampling->sendRequest($review);
        
        // Generate actionable recommendations
        return new TextResult($this->formatReview($aiAnalysis));
    }
}
```

### 📊 Data Analysis Agent
```php
class DataInsightAgent implements SamplingAwareToolInterface, StreamableToolInterface
{
    public function execute(array $arguments): ToolResultInterface
    {
        $this->notifier->notify("Analyzing dataset...", 0.1);
        
        // Multi-step reasoning process
        $steps = [
            'Identify patterns and anomalies',
            'Generate statistical insights',
            'Create visualizations',
            'Recommend actions'
        ];
        
        $insights = [];
        foreach ($steps as $i => $step) {
            $request = $this->sampling->createRequest([
                'role' => 'user',
                'content' => "$step for this data: {$arguments['data']}"
            ]);
            
            $insights[] = $this->sampling->sendRequest($request);
            $this->notifier->notify("Completed: $step", ($i + 1) / count($steps));
        }
        
        return new TextResult($this->compileReport($insights));
    }
}
```

### 🤝 Customer Support Agent
```php
class SupportAgent implements SamplingAwareToolInterface
{
    public function execute(array $arguments): ToolResultInterface
    {
        // Load customer context
        $context = $this->loadCustomerHistory($arguments['customer_id']);
        
        // AI determines best response strategy
        $strategy = $this->sampling->createRequest([
            'role' => 'system',
            'content' => 'You are an expert customer support agent.'
        ], [
            'role' => 'user',
            'content' => "Customer issue: {$arguments['issue']}
                         History: $context
                         Determine the best resolution approach."
        ]);
        
        $approach = $this->sampling->sendRequest($strategy);
        
        // Execute the recommended actions
        return $this->executeResolution($approach, $arguments);
    }
}
```

## 🚀 Quick Start: Build Your First Agent

### 1. Requirements

- PHP >=8.2
- Symfony >=6.4

### 2. Install Symfony MCP Server

#### Create the configuration file config/packages/klp_mcp_server.yaml and paste into it:

    ```yaml
    klp_mcp_server:
        enabled: true
        server:
            name: 'My MCP Server'
            version: '1.0.0'
        default_path: 'mcp'
        ping:
            enabled: true  # Read the warning section in the default configuration file before disable it
            interval: 30
        server_providers: ['streamable_http','sse']
        sse_adapter: 'cache'
        adapters:
            cache:
                prefix: 'mcp_sse_'
                ttl: 100
        tools:
            - KLP\KlpMcpServer\Services\ToolService\Examples\CodeAnalyzerTool     # Agentic tool sample
            - KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool
            - KLP\KlpMcpServer\Services\ToolService\Examples\ProfileGeneratorTool
            - KLP\KlpMcpServer\Services\ToolService\Examples\StreamingDataTool
            - KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool
        prompts:
            - KLP\KlpMcpServer\Services\PromptService\Examples\CodeReviewPrompt   # Agentic prompt sample
            - KLP\KlpMcpServer\Services\PromptService\Examples\HelloWorldPrompt
        resources:
            - KLP\KlpMcpServer\Services\ResourceService\Examples\HelloWorldResource
            - KLP\KlpMcpServer\Services\ResourceService\Examples\ProjectSummaryResource # Agentic resource sample
        resources_templates:
            - KLP\KlpMcpServer\Services\ResourceService\Examples\DynamicAnalysisResource # Agentic resource template sample
            - KLP\KlpMcpServer\Services\ResourceService\Examples\McpDocumentationResource
    ```
   For more detailed explanations, you can open the default configuration file
   [from that link.](src/Resources/config/packages/klp_mcp_server.yaml)

#### Install the package via Composer:

   ```bash
   composer require klapaudius/symfony-mcp-server
   ```

#### Add routes in your `config/routes.yaml`

```yaml
klp_mcp_server:
    resource: '@KlpMcpServerBundle/Resources/config/routes.php'
    type: php
```

**You're all done!** Upon completing this setup, your project will include 3 new API endpoints:

- **Streaming Endpoint for MCP Clients**: `GET /{default_path}/sse`
- **Request Submission Endpoint**: `POST /{default_path}/messages`
- **Streamable HTTP Endpoint**: `GET|POST /{default_path}`

### Docker Setup (Optional)

The project includes a Docker setup that can be used for development. The Docker setup includes Nginx, PHP-FPM with Redis extension, and Redis server.

For detailed instructions on how to set up and use the Docker containers, please refer to the [Development Guidelines](CONTRIBUTING.md#docker-setup).


### 3. Create Your First Tool

```bash
# Generate a new tool
php bin/console make:mcp-tool MyCustomTool

# Test your tool locally
php bin/console mcp:test-tool MyCustomTool --input='{"task":"analyze this code"}'
```

### 4. Connect AI Clients

Your agents are now accessible to:
- 🤖 Claude Desktop / Claude.ai
- 🧠 Custom AI applications
- 🔗 Any MCP-compatible client

## 🏗️ Architecture for Agent Builders

### Secure Agent Communication
- **StreamableHTTP**: Direct, secure agent-to-client communication
- **SSE (Server-Sent Events)**: Real-time updates for long-running agent tasks
- **No STDIO**: Enterprise-safe, no system exposure

### Scalable Agent Infrastructure
- **Pub/Sub Messaging**: Handle multiple agent sessions concurrently
- **Redis/Cache Adapters**: Scale your agent platform horizontally
- **Progress Streaming**: Real-time feedback for complex agent operations

### Agent Development Tools
- **MCP Inspector**: Visualize and debug agent behavior
- **Test Commands**: Rapid agent development and testing
- **Sampling Debugger**: Understand AI decision-making

## 🎓 Agent Development Resources

- 📖 **[Building Intelligent Tools](https://github.com/klapaudius/symfony-mcp-server/blob/master/docs/building_tools.md)**: Complete guide to creating AI-powered tools
- 🧠 **[Sampling Documentation](https://github.com/klapaudius/symfony-mcp-server/blob/master/docs/sampling.md)**: Master agent reasoning capabilities
- 🎭 **[Prompt Engineering](https://github.com/klapaudius/symfony-mcp-server/blob/master/docs/building_prompts.md)**: Design agent behaviors and personalities
- 📚 **[Resource Management](https://github.com/klapaudius/symfony-mcp-server/blob/master/docs/building_resources.md)**: Give agents access to knowledge

## 🌟 Join the Agent Revolution

Build the next generation of AI-powered applications with Symfony MCP Server. Your tools aren't just functions anymore – they're intelligent agents capable of reasoning, learning, and evolving.

### Community

- 💬 [GitHub Discussions](https://github.com/klapaudius/symfony-mcp-server/discussions): Share your agent creations
- 🐛 [Issue Tracker](https://github.com/klapaudius/symfony-mcp-server/issues): Report bugs and request features
- 🌟 [Examples](https://github.com/klapaudius/symfony-mcp-server/tree/master/src/Services/ToolService/Examples): Learn from working agents

## 📜 License

MIT License - Build freely!

---

*Built with ❤️ by [Boris AUBE](https://github.com/klapaudius) and the [contributors](https://github.com/klapaudius/symfony-mcp-server/contributors) - Inspired by [OP.GG/laravel-mcp-server](https://github.com/opgginc/laravel-mcp-server)*
