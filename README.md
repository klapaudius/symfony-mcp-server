<h1 align="center">Symfony MCP Server</h1>

<p align="center">
  A powerful Symfony package to build a Model Context Protocol Server seamlessly
</p>

<p align="center">
<a href="https://github.com/klapaudius/symfony-mcp-server/actions"><img src="https://github.com/klapaudius/symfony-mcp-server/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/klapaudius/symfony-mcp-server" >  <img src="https://codecov.io/gh/klapaudius/symfony-mcp-server/graph/badge.svg?token=5FXOJVXPZ1" alt="Coverage"/></a>
<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/l/klapaudius/symfony-mcp-server" alt="License"></a>

[//]: # (<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/v/klapaudius/symfony-mcp-server" alt="Latest Stable Version"></a>)
[//]: # (<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/dt/klapaudius/symfony-mcp-server" alt="Total Downloads"></a>)
</p>

## Overview

Symfony MCP Server is a powerful package designed to streamline the implementation of Model Context Protocol (MCP) servers in Symfony applications. This package **utilizes StreamableHTTP and/or Server-Sent Events (SSE)** transport, providing a secure and controlled integration methods.

### Why not STDIO?

While stdio is straightforward and widely used in MCP implementations, it has significant security implications for enterprise environments:

- **Security Risk**: STDIO transport potentially exposes internal system details and API specifications
- **Data Protection**: Organizations need to protect proprietary API endpoints and internal system architecture
- **Control**: StreamableHTTP or SSE offers better control over the communication channel between LLM clients and your application

By implementing the MCP server with StreamableHTTP or SSE transport, enterprises can:

- Expose only the necessary tools and resources while keeping proprietary API details private
- Maintain control over authentication and authorization processes

Key benefits:

- Seamless and rapid implementation of StreamableHTTP and/or SSE in existing Symfony projects
- Support for the latest Symfony and PHP versions
- Efficient server communication and real-time data processing
- Enhanced security for enterprise environments

## Key Features

- Real-time communication support through StreamableHTTP and/or Server-Sent Events (SSE) integration
- Implementation of tools and resources compliant with Model Context Protocol specifications
- Support of streaming tools with progres notifications
- Support different types of tool results such as Text, Image, Audio, or Resource
- Adapter-based design architecture with Pub/Sub messaging pattern

## Requirements

- PHP >=8.2
- Symfony >=7

## Installation

1. Create the configuration file config/packages/klp_mcp_server.yaml and paste into it:

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
            - KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool
            - KLP\KlpMcpServer\Services\ToolService\Examples\ProfileGeneratorTool
            - KLP\KlpMcpServer\Services\ToolService\Examples\StreamingDataTool
            - KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool
        prompts:
            - KLP\KlpMcpServer\Services\PromptService\Examples\HelloWorldPrompt
        resources:
            - KLP\KlpMcpServer\Services\ResourceService\Examples\HelloWorldResource
        resources_templates:
            - KLP\KlpMcpServer\Services\ResourceService\Examples\McpDocumentationResource
    ```
   For more detailed explanations, you can open the default configuration file
   [from that link.](src/Resources/config/packages/klp_mcp_server.yaml)

2. Install the package via Composer:

   ```bash
   composer require klapaudius/symfony-mcp-server
   ```

3. Add routes in your `config/routes.yaml`

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

For detailed instructions on how to set up and use the Docker containers, please refer to the [Development Guidelines](docs/development_guidelines.md#docker-setup).

## Strongly Recommended
Enhance your application's security by implementing OAuth2 Authentication. You can use the [klapaudius/oauth-server-bundle](https://github.com/klapaudius/FOSOAuthServerBundle) or any other compatible OAuth2 solution.

## Basic Usage

### Creating and Adding Custom Tools

The package provides convenient commands to generate new tools:

```bash
php bin/console make:mcp-tool MyCustomTool
```

This command:

- Handles various input formats (spaces, hyphens, mixed case)
- Automatically converts the name to the proper case format
- Creates a properly structured tool class in `src/MCP/Tools`
- Offers to automatically register the tool in your configuration

You can also manually create and register tools in `config/packages/klp_mcp_server.yaml`:

```php
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

class MyCustomTool implements StreamableToolInterface
{
    // Tool implementation
}
```

### Testing MCP Tools

The package includes a special command for testing your MCP tools without needing a real MCP client:

```bash
# Test a specific tool interactively
php bin/console mcp:test-tool MyCustomTool

# List all available tools
php bin/console mcp:test-tool --list

# Test with specific JSON input
php bin/console mcp:test-tool MyCustomTool --input='{"param1":"value"}'
```

This helps you rapidly develop and debug tools by:

- Showing the tool's input schema and validating inputs
- Executing the tool with your provided input
- Displaying formatted results or detailed error information
- Displaying progress notifications for streaming tool
- Supporting complex input types including objects and arrays

**For deep diving into tools creation please take a look at dedicated documentation [Here](https://github.com/klapaudius/symfony-mcp-server/blob/master/docs/building_tools.md)**

### Creating and Adding Custom Prompts

The package provides convenient commands to generate new prompts:

```bash
php bin/console make:mcp-prompt MyCustomPrompt
```

This command:

- Handles various input formats (spaces, hyphens, mixed case)
- Automatically converts the name to the proper kebab-case format
- Creates a properly structured prompt class in `src/MCP/Prompts`
- Offers to automatically register the prompt in your configuration

You can also manually create and register prompts in `config/packages/klp_mcp_server.yaml`:

```php
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;

class MyCustomPrompt implements PromptInterface
{
    // Prompt implementation
}
```

### Testing MCP Prompts

The package includes a command for testing your MCP prompts without needing a real MCP client:

```bash
# Test a specific prompt interactively
php bin/console mcp:test-prompt MyCustomPrompt

# List all available prompts
php bin/console mcp:test-prompt --list

# Test with specific arguments
php bin/console mcp:test-prompt MyCustomPrompt --arguments='{"topic":"AI","tone":"professional"}'
```

This helps you rapidly develop and debug prompts by:

- Showing the prompt's argument schema and validating inputs
- Executing the prompt with your provided arguments
- Displaying formatted message results with proper role assignments
- Supporting complex argument types including objects and arrays
- Demonstrating multi-modal content (text, images, audio, resources)

**For deep diving into prompts creation please take a look at dedicated documentation [Here](https://github.com/klapaudius/symfony-mcp-server/blob/master/docs/building_prompts.md)**

### Visualizing with Inspector

You can also use the Model Context Protocol Inspector to visualize and test your MCP tools:

```bash
# Run the MCP Inspector without installation
npx @modelcontextprotocol/inspector node build/index.js
```
This will typically open a web interface at `localhost:6274`. To test your MCP server:

1. **Warning**: `symfony server:start` CANNOT be used with this package because it cannot handle multiple PHP connections simultaneously. Since MCP SSE requires processing multiple connections concurrently, you must use one of these alternatives:

     - Nginx + PHP-FPM
     - Apache + PHP-FPM
     - Custom Docker setup
     - Any web server that properly supports SSE streaming  
     - 
2. In the Inspector interface, chose the protocol and enter the corresponding endpoint url

|  MCP Specification version   | Connection Url pattern                                                         |
|:----------------------------:|--------------------------------------------------------------------------------|
|       2024-11-05 (SSE)       | `http(s)://[your-web-server]/[default_path]/sse`                               |
| 2025-03-26 (Streamable HTTP) | `http(s)://[your-web-server]/[default_path]`                                   |
|                              | `default_path` is defined in your `config/packages/klp_mcp_server.yaml` file.  |

3. Connect and explore available items visually

## Advanced Features

### Pub/Sub Architecture with Adapters

The package implements a publish/subscribe (pub/sub) messaging pattern through its adapter system:

1. **Publisher (Server)**: When clients send requests (e.g. `/messages` endpoint for SSE connection), the server processes these requests and publishes responses through the configured adapter.

2. **Message Broker (Adapter)**: The adapter maintains message queues for each client, identified by unique client IDs. This provides a reliable asynchronous communication layer.

3. **Subscriber (SSE Connection)**: Long-lived SSE connections subscribe to messages for their respective clients and deliver them in real-time.

This architecture enables:

- Scalable real-time communication
- Reliable message delivery even during temporary disconnections
- Efficient handling of multiple concurrent client connections
- Potential for distributed server deployments

### Redis Adapter Configuration (Optional)

A Redis adapter can be configured as follows:


```yaml
klp_mcp_server:
    # ...
    sse_adapter: 'redis'
    adapters:
        redis:
            prefix: 'mcp_sse_'  # Prefix for Redis keys
            host: 'localhost'   # Change it as needed
            ttl: 100            # Message TTL in seconds
```

## Resources

The package provides a flexible resource management system that allows you to store and retrieve resources from different providers (file system, database, etc.).

### Configuration

Configure resources in your `config/packages/klp_mcp_server.yaml` file:

```yaml
klp_mcp_server:
    # ...
    resources:
        - App\MCP\Resources\MyCustomResource
    resources_templates:
        - App\MCP\Resources\MyCustomResourceTemplate
```

### Usage

### Creating Custom Resource

```php
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;

class MyCustomResource implements ResourceInterface
{
    // Resource implementation
}
```
Then register your resource in the configuration:

```yaml
klp_mcp_server:
    # ...
    resources:
      - App\MCP\Resources\MyCustomResource
```

### Creating Custom Resource Template

You can create custom resource templates by implementing the `ResourceTemplateInterface`:

```php
use KLP\KlpMcpServer\Services\ResourceService\ResourceTemplateInterface;

class MyCustomResourceTemplate implements ResourceTemplateInterface
{
    // Implement the required methods
}
```

Then register your resource template in the configuration:

```yaml
klp_mcp_server:
    # ...
    resources_templates:
      - App\MCP\Resources\MyCustomResourceTemplate
```

**For deep diving into resources' management, please take a look at dedicated documentation [Here](https://github.com/klapaudius/symfony-mcp-server/blob/master/docs/building_resources.md)**

## Roadmap
We are committed to actively pursuing the following key initiatives to enhance the package's functionality and ensure compliance with evolving standards.

- **Core Features:**
  - ✅ Resources implementation compliant with MCP specification.
  - ✅ Support for Streamable HTTP (as specified in MCP 2025-03-26 version).
  - ✅ Prompts implementation compliant with MCP specification.
- **Additional Adaptaters:**
  - Support for more Pub/Sub adapters (e.g., RabbitMQ).

## Credits
- Boris AUBE and [all contributors](https://github.com/klapaudius/symfony-mcp-server/contributors)
- Inspired by [OP.GG/laravel-mcp-server](https://github.com/opgginc/laravel-mcp-server)

## License

This project is distributed under the MIT license.
