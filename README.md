<h1 align="center">Symfony MCP Server (work in progress)</h1>

<p align="center">
  A powerful Symfony package to build a Model Context Protocol Server seamlessly
</p>

<p align="center">
<a href="https://github.com/klapaudius/symfony-mcp-server/actions"><img src="https://github.com/klapaudius/symfony-mcp-server/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://codecov.io/gh/klapaudius/symfony-mcp-server" >  <img src="https://codecov.io/gh/klapaudius/symfony-mcp-server/graph/badge.svg?token=5FXOJVXPZ1"/></a>

[//]: # (<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/dt/klapaudius/symfony-mcp-server" alt="Total Downloads"></a>)
[//]: # (<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/v/klapaudius/symfony-mcp-server" alt="Latest Stable Version"></a>)
[//]: # (<a href="https://packagist.org/packages/klapaudius/symfony-mcp-server"><img src="https://img.shields.io/packagist/l/klapaudius/symfony-mcp-server" alt="License"></a>)
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

- Real-time communication support through Server-Sent Events (SSE) integration
- Implementation of tools and resources compliant with Model Context Protocol specifications
- Adapter-based design architecture with Pub/Sub messaging pattern (starting with Redis, more adapters planned)
- Simple routing and middleware configuration

## Requirements

- PHP >=8.2
- Symfony >=7.x

## Installation

1. Install the package via Composer:

   ```bash
   composer require klapaudius/symfony-mcp-server
   ```
2. Configure your redis connection:

    ```dotenv
    MCP_REDIS_CONNECTION="redis://localhost:6379/mcp" # change it to your needs
    ```

## Basic Usage

### Creating and Adding Custom Tools

The package provides convenient Artisan commands to generate new tools:

```bash
php bin/console make:mcp-tool MyCustomTool
```

This command:

- Handles various input formats (spaces, hyphens, mixed case)
- Automatically converts the name to proper case format
- Creates a properly structured tool class in `app/MCP/Tools`
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
php bin/console mcp:test-tool MyCustomTool --input='{"param":"value"}'
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

Documentation still in progress
===============================

### The following provides from the forked repository and is not relevant to this version.

This will typically open a web interface at `localhost:6274`. To test your MCP server:

1. **Warning**: `php artisan serve` CANNOT be used with this package because it cannot handle multiple PHP connections simultaneously. Since MCP SSE requires processing multiple connections concurrently, you must use one of these alternatives:

   * **Laravel Octane** (Easiest option):
     ```bash
     # Install and set up Laravel Octane with FrankenPHP (recommended)
     composer require laravel/octane
     php artisan octane:install --server=frankenphp
     
     # Start the Octane server
     php artisan octane:start
     ```
     
     > **Important**: When installing Laravel Octane, make sure to use FrankenPHP as the server. The package may not work properly with RoadRunner due to compatibility issues with SSE connections. If you can help fix this RoadRunner compatibility issue, please submit a Pull Request - your contribution would be greatly appreciated!
     
     For details, see the [Laravel Octane documentation](https://laravel.com/docs/12.x/octane)
     
   * **Production-grade options**:
     - Nginx + PHP-FPM
     - Apache + PHP-FPM
     - Custom Docker setup
     - Any web server that properly supports SSE streaming  
2. In the Inspector interface, enter your Laravel server's MCP SSE URL (e.g., `http://localhost:8000/mcp/sse`)  
3. Connect and explore available tools visually

The SSE URL follows the pattern: `http://[your-laravel-server]/[default_path]/sse` where `default_path` is defined in your `config/mcp-server.php` file.

## Advanced Features

### Pub/Sub Architecture with SSE Adapters

The package implements a publish/subscribe (pub/sub) messaging pattern through its adapter system:

1. **Publisher (Server)**: When clients send requests to the `/message` endpoint, the server processes these requests and publishes responses through the configured adapter.

2. **Message Broker (Adapter)**: The adapter (e.g., Redis) maintains message queues for each client, identified by unique client IDs. This provides a reliable asynchronous communication layer.

3. **Subscriber (SSE Connection)**: Long-lived SSE connections subscribe to messages for their respective clients and deliver them in real-time.

This architecture enables:

- Scalable real-time communication
- Reliable message delivery even during temporary disconnections
- Efficient handling of multiple concurrent client connections
- Potential for distributed server deployments

### Redis Adapter Configuration

The default Redis adapter can be configured as follows:

```php
'sse_adapter' => 'redis',
'adapters' => [
    'redis' => [
        'prefix' => 'mcp_sse_',    // Prefix for Redis keys
        'connection' => 'default', // Redis connection from database.php
        'ttl' => 100,              // Message TTL in seconds
    ],
],
```

## Environment Variables

The package supports the following environment variables to allow configuration without modifying the config files:

| Variable | Description | Default |
|----------|-------------|--------|
| `MCP_SERVER_ENABLED` | Enable or disable the MCP server | `true` |
| `MCP_REDIS_CONNECTION` | Redis connection name from database.php | `default` |

### Example .env Configuration

```
# Disable MCP server in specific environments
MCP_SERVER_ENABLED=false

# Use a specific Redis connection for MCP
MCP_REDIS_CONNECTION=mcp
```

## Credits
- Boris AUBE and [all contributors](https://github.com/klapaudius/symfony-mcp-server/contributors)
- Inspired by [OP.GG/laravel-mcp-server](https://github.com/opgginc/laravel-mcp-server)

## License

This project is distributed under the MIT license.
