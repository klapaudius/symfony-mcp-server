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

Symfony MCP Server is a powerful package designed to streamline the implementation of Model Context Protocol (MCP) servers in Symfony applications. This package **utilizes Server-Sent Events (SSE)** transport, providing a secure and controlled integration method.

### Why SSE instead of STDIO?

While stdio is straightforward and widely used in MCP implementations, it has significant security implications for enterprise environments:

- **Security Risk**: STDIO transport potentially exposes internal system details and API specifications
- **Data Protection**: Organizations need to protect proprietary API endpoints and internal system architecture
- **Control**: SSE offers better control over the communication channel between LLM clients and your application

By implementing the MCP server with SSE transport, enterprises can:

- Expose only the necessary tools and resources while keeping proprietary API details private
- Maintain control over authentication and authorization processes

Key benefits:

- Seamless and rapid implementation of SSE in existing Symfony projects
- Support for the latest Symfony and PHP versions
- Efficient server communication and real-time data processing
- Enhanced security for enterprise environments

## Key Features

- Real-time communication support through Server-Sent Events (SSE) integration specified in the MCP 2024-11-05 version (Streamable HTTP from 2025-03-26 version is planned)
- Implementation of tools and resources compliant with Model Context Protocol specifications
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
        server_provider: 'sse'
        sse_adapter: 'cache'
        adapters:
            cache:
                prefix: 'mcp_sse_'
                ttl: 100
        tools:
            - KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool
            - KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool
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

**You're all done!** Upon completing this setup, your project will include two new API endpoints:

- **Streaming Endpoint for MCP Clients**: `GET /{default_path}/sse`
- **Request Submission Endpoint**: `POST /{default_path}/messages`

## Strongly Recommended
Enhance your application's security by implementing OAuth2 Authentication. You can use the [klapaudius/oauth-server-bundle](https://github.com/klapaudius/FOSOAuthServerBundle) or any other compatible OAuth2 solution.

## Basic Usage

### Creating and Adding Custom Tools

The package provides convenient Artisan commands to generate new tools:

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
use KLP\KlpMcpServer\Services\ToolService\ToolInterface;

class MyCustomTool implements ToolInterface
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
- Supporting complex input types including objects and arrays

### Visualizing MCP Tools with Inspector

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
2. In the Inspector interface, enter your Symfony server's MCP SSE URL (e.g., `http://localhost:8000/mcp/sse`)  
3. Connect and explore available tools visually

The SSE URL follows the pattern: `http://[your-web-server]/[default_path]/sse` where `default_path` is defined in your `config/packages/klp_mcp_server.yaml` file.

## Advanced Features

### Pub/Sub Architecture with SSE Adapters

The package implements a publish/subscribe (pub/sub) messaging pattern through its adapter system:

1. **Publisher (Server)**: When clients send requests to the `/messages` endpoint, the server processes these requests and publishes responses through the configured adapter.

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

## Roadmap
### Version 0.8.0 (Current)

- **Initial Release:**
Basic implementation of the Model Context Protocol (MCP) server using Server-Sent Events (SSE).
- **Core Features:**
  - Real-time communication support through SSE.
  - Basic tool implementation compliant with MCP specifications.
  - Redis adapter for Pub/Sub messaging pattern.
- **Documentation:** Basic setup and usage instructions.
### Version 0.9.0 (Next)

- **Core Features:**
  - **Refactoring:** Refactor `TestMcpToolCommand` to reduce technical debt and improve code maintainability.
  - **Testing Enhancements:** Enhance test coverage to achieve an acceptable and robust ratio, ensuring reliability and stability.
  - **New Adapter**: Symfony Cache adpater for Pub/Sub messaging pattern
- **Documentation:**
  - **Examples and Use Cases:** Include additional examples and use cases to illustrate practical applications and best practices.

### Version 1.0.0
- **Core Features:**
  - Basic resources implementation compliant with MCP specification.
  - Support for Streamable HTTP (as specified in MCP 2025-03-26 version).
- **Additional Adaptaters:**
  - Support for more Pub/Sub adapters (e.g., RabbitMQ).
- **Documentation:**
  - Expanded documentation with more detailed examples and use cases.
  - Tutorials and best practices for implementation.
  - Establish guidelines for contributing.


## Credits
- Boris AUBE and [all contributors](https://github.com/klapaudius/symfony-mcp-server/contributors)
- Inspired by [OP.GG/laravel-mcp-server](https://github.com/opgginc/laravel-mcp-server)

## License

This project is distributed under the MIT license.
