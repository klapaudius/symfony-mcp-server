<h1 align="center">Symfony MCP Server</h1>

<p align="center">
  A powerful Symfony package to build a Model Context Protocol Server seamlessly and
  <strong>build Intelligent AI Agents.</strong><br>
  Transform your Symfony applications into powerful AI-driven systems
</p>

<p align="center">
<a href="https://github.com/klapaudius/symfony-mcp-server/actions"><img src="https://github.com/klapaudius/symfony-mcp-server/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/klapaudius/symfony-mcp-server" >  <img src="https://codecov.io/gh/klapaudius/symfony-mcp-server/graph/badge.svg?token=5FXOJVXPZ1" alt="Coverage"/></a>
<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/l/klapaudius/symfony-mcp-server" alt="License"></a>
<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/v/klapaudius/symfony-mcp-server" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/dt/klapaudius/symfony-mcp-server" alt="Total Downloads"></a>
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

**Key Features:**
- 🛠️ **Tools**: Create powerful, executable functions that LLM can invoke to interact with your application
- 💬 **Prompts**: Define conversation starters and templates to guide AI behavior and interactions
- 📚 **Resources**: Expose structured data and documents that AI can read and reason about
- 🧠 **Sampling**: Enable tools to consult AI models mid-execution for intelligent decision-making
- 📊 **Progress Streaming**: Real-time progress notifications for long-running operations
- 🎨 **Multi-Modal Results**: Support for Text, Image, Audio, and Resource outputs from tools
- 🔌 **Flexible Architecture**: Adapter-based design with Pub/Sub messaging for scalable deployments

## 🚀 Agent-First Features

### 🧪 Sampling: The Core of Agentic Behavior (v1.4.0+)

Transform your tools into autonomous agents that can think and reason:

```php
class IntelligentAnalyzer implements SamplingAwareToolInterface
{
    private SamplingClient $samplingClient;

    public function execute(array $arguments): ToolResultInterface
    {
        // Check if sampling is available
        if ($this->samplingClient !== null && $this->samplingClient->isEnabled()) {
            try {
                // Let AI analyze and reason about complex data
                $response = $this->samplingClient->createTextRequest(
                    "Analyze this data and suggest optimizations: {$arguments['data']}",
                    new ModelPreferences(
                        [['name' => 'claude-3-sonnet']],
                        0.3, // costPriority
                        0.8, // intelligencePriority
                        0.2  // speedPriority
                    ),
                    'You are a data analysis expert.',
                    2000
                );
                
                // Execute actions based on AI reasoning
                return new TextToolResult($response->getContent()->getText());
            } catch (\Exception $e) {
                // Fallback to basic analysis
                return new TextToolResult('Basic analysis: Data structure appears valid.');
            }
        }
        
        return new TextToolResult('Advanced analysis requires AI capabilities.');
    }
    
    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
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
    private SamplingClient $samplingClient;

    public function execute(array $arguments): ToolResultInterface
    {
        // Check if sampling is available
        if ($this->samplingClient !== null && $this->samplingClient->isEnabled()) {
            try {
                // AI analyzes code for patterns, security, and best practices
                $review = $this->samplingClient->createTextRequest(
                    "Review this code for security vulnerabilities, performance issues, and suggest improvements: {$arguments['code']}",
                    new ModelPreferences(
                        [['name' => 'claude-3-sonnet']],
                        0.2, // costPriority
                        0.8, // intelligencePriority
                        0.2  // speedPriority
                    ),
                    'You are a senior code reviewer with expertise in security and performance.',
                    2000
                );
                
                // Generate actionable recommendations
                return new TextToolResult($this->formatReview($review->getContent()->getText()));
            } catch (\Exception $e) {
                // Fallback to basic analysis
                return new TextToolResult('Basic code review: Structure appears valid.');
            }
        }
        
        return new TextToolResult('Advanced code review requires AI capabilities.');
    }
    
    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }
}
```

### 📊 Data Analysis Agent
```php
class DataInsightAgent implements SamplingAwareToolInterface, StreamableToolInterface
{
    private SamplingClient $samplingClient;
    private ?ProgressNotifierInterface $progressNotifier = null;

    public function execute(array $arguments): ToolResultInterface
    {
        // Check if sampling is available
        if ($this->samplingClient !== null && $this->samplingClient->isEnabled()) {
            try {
                // Multi-step reasoning process
                $steps = [
                    'Identify patterns and anomalies',
                    'Generate statistical insights',
                    'Create visualizations',
                    'Recommend actions'
                ];
                $this->progressNotifier?->sendProgress(
                    progress: 0,
                    total: count($steps)+1,
                    message: "Analyzing dataset..."
                );
                
                $insights = [];
                foreach ($steps as $i => $step) {
                    $response = $this->samplingClient->createTextRequest(
                        "$step for this data: {$arguments['data']}",
                        new ModelPreferences(
                            [['name' => 'claude-3-sonnet']],
                            0.2, // costPriority
                            0.8, // intelligencePriority
                            0.2  // speedPriority
                        ),
                        'You are a data analysis expert.',
                        2000
                    );
                    $insights[] = $response->getContent()->getText();
                    $this->progressNotifier?->sendProgress(
                        progress: $i+1,
                        total: count($steps)+1,
                        message: $step
                    );
                }
                
                return new TextToolResult($this->compileReport($insights));
            } catch (\Exception $e) {
                return new TextToolResult('Basic data analysis: Dataset appears well-formed.');
            }
        }
        
        return new TextToolResult('Advanced data analysis requires AI capabilities.');
    }
    
    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }
    
    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        $this->progressNotifier = $progressNotifier;
    }
}
```

### 🤝 Customer Support Agent
```php
class SupportAgent implements SamplingAwareToolInterface
{
    private SamplingClient $samplingClient;

    public function execute(array $arguments): ToolResultInterface
    {
        // Check if sampling is available
        if ($this->samplingClient !== null && $this->samplingClient->isEnabled()) {
            try {
                // Load customer context
                $context = $this->loadCustomerHistory($arguments['customer_id']);
                
                // AI determines best response strategy
                $response = $this->samplingClient->createTextRequest(
                    "Customer issue: {$arguments['issue']}\nHistory: $context\nDetermine the best resolution approach.",
                    new ModelPreferences(
                        [['name' => 'claude-3-sonnet']],
                        0.2, // costPriority
                        0.8, // intelligencePriority
                        0.2  // speedPriority
                    ),
                    'You are an expert customer support agent.',
                    2000
                );
                
                // Send back the strategy for user approval
                return new TextToolResult($response->getContent()->getText());
            } catch (\Exception $e) {
                return new TextToolResult('Standard support response: We will review your issue and respond within 24 hours.');
            }
        }
        
        return new TextToolResult('Personalized support requires AI capabilities.');
    }
    
    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
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

### 4. Visualizing with Inspector

You can use the Model Context Protocol Inspector to visualize and test your MCP tools:

```bash
# Run the MCP Inspector without installation
npx @modelcontextprotocol/inspector node build/index.js
```
This will typically open a web interface at `localhost:6274`. To test your MCP server:

### 5. Connect AI Clients

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

### Current Available MCP Features

| Ressources | Prompts | Tools | Discovery | Sampling | Roots | Elicitation |
|:----------:|:-------:|:-----:|:---------:|:--------:|:-----:|:-----------:|
|     ✅      |    ✅    |   ✅   |   ❌   |    ✅     |   ❌   |   ❌   |

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

## 📰 MCP Registries referencing

- https://mcpreview.com/mcp-servers/klapaudius/symfony-mcp-server
- https://mcp.so/server/symfony-mcp-server/klapaudius

---

*Built with ❤️ by [Boris AUBE](https://github.com/klapaudius) and the [contributors](https://github.com/klapaudius/symfony-mcp-server/contributors) - Inspired by [OP.GG/laravel-mcp-server](https://github.com/opgginc/laravel-mcp-server)*
