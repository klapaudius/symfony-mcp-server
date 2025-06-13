# Symfony MCP Server Development Guidelines

This document provides essential information for developers working on the Symfony MCP Server project.

## Build/Configuration Instructions

### Prerequisites
- PHP 8.2 or higher
- Symfony 7.0
- Composer

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/klapaudius/symfony-mcp-server.git
   ```

2. Create a new Symfony project (if you don't have one already):
   ```bash
   composer create-project symfony/skeleton my-symfony-project
   cd my-symfony-project
   ```

3. Add the cloned repository as a local repository in your Symfony project's composer.json:
   ```json
   {
       "repositories": [
           {
               "type": "path",
               "url": "../symfony-mcp-server"
           }
       ]
   }
   ```
   Note: Adjust the path "../symfony-mcp-server" to match the relative path from your Symfony project to the cloned repository.

4. Require the package from the local repository:
   ```bash
   composer require klapaudius/symfony-mcp-server:@dev
   ```

5. Create the configuration file `config/packages/klp_mcp_server.yaml` with the following content:
   ```yaml
   klp_mcp_server:
       enabled: true
       server:
           name: 'My MCP Server'
           version: '1.0.0'
       default_path: 'mcp'
       ping:
           enabled: true
           interval: 30
       server_providers: ['streamable_http', 'sse']  # Available options: 'sse', 'streamable_http'
       sse_adapter: 'cache'
       adapters:
           redis:
               prefix: 'mcp_sse_'
               host: 'localhost'  # Change as needed
               ttl: 100
       tools:
           - KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool
           - KLP\KlpMcpServer\Services\ToolService\Examples\StreamingDataTool
           - KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool
   ```

6. Add routes in your `config/routes.yaml`:
   ```yaml
   klp_mcp_server:
       resource: '@KlpMcpServerBundle/Resources/config/routes.php'
       type: php
   ```

### Important Configuration Notes

- **Web Server**: This package cannot be used with `symfony server:start` as it requires processing multiple connections concurrently. Use Nginx + PHP-FPM, Apache + PHP-FPM, or a custom Docker setup instead.
- **Redis Configuration**: Ensure your Redis server is properly configured and accessible at the host specified in the configuration.
- **Security**: It's strongly recommended to implement OAuth2 Authentication for production use.
- **Protocol Support**: This bundle supports two protocols:
  - **SSE (Server-Sent Events)**: The legacy protocol for real-time communication.
  - **StreamableHTTP**: The actual protocol that works over standard HTTP connection or Streaming if needed.

### Docker Setup

The project includes a Docker setup that can be used for development. The Docker setup includes:
- Nginx web server
- PHP-FPM with Redis extension
- Redis server

To set up and use the Docker containers:

1. Navigate to the docker directory:
   ```bash
   cd docker
   ```

2. Configure the environment variables by creating a `.env.local` file:
   ```bash
   # Example configuration
   COMPOSE_PROJECT_NAME=mcp

   NGINX_IP=172.20.0.2
   NGINX_EXPOSED_PORT=8080

   PHP_IP=172.20.0.3

   REDIS_IP=172.20.0.4

   NETWORK_SUBNET=172.20.0.0/24
   ```
   Note: Adjust the IP addresses and port as needed for your environment.

3. Build and start the Docker containers:
   ```bash
   docker-compose up -d
   ```

4. Access the application in your browser at `http://localhost:8080` (or the port you specified in NGINX_EXPOSED_PORT).

5. To stop the containers:
   ```bash
   docker-compose down
   ```

6. Update your Symfony configuration to use the Redis server in the Docker container:
   ```yaml
   # config/packages/klp_mcp_server.yaml
   klp_mcp_server:
       # ...
       adapters:
           redis:
               prefix: 'mcp_sse_'
               host: 'redis'  # Use the service name from docker-compose.yml
               ttl: 100
   ```

## Testing Information

### Running Tests

The project uses PHPUnit for testing. To run all tests:

```bash
vendor/bin/phpunit
```

To run a specific test file:

```bash
vendor/bin/phpunit tests/path/to/TestFile.php
```

To generate code coverage reports:

```bash
vendor/bin/phpunit --coverage-html build/coverage
```

### Adding New Tests

1. **Test Location**: Place tests in the `tests/` directory, mirroring the structure of the `src/` directory.
2. **Naming Convention**: Name test classes with the suffix `Test` (e.g., `DataUtilTest.php`).
3. **Test Size**: Use PHPUnit attributes to indicate test size:
   ```php
   #[Small]  // For unit tests
   #[Medium] // For integration tests
   #[Large]  // For system tests
   ```
4. **Mocking**: Use PHPUnit's mocking capabilities for dependencies:
   ```php
   $mockTransport = $this->createMock(SseTransportInterface::class);
   ```

### Example Test

Here's a simple test for the `DataUtil` class:

```php
<?php

namespace KLP\KlpMcpServer\Tests\Utils;

use KLP\KlpMcpServer\Data\Requests\NotificationData;
use KLP\KlpMcpServer\Data\Requests\RequestData;
use KLP\KlpMcpServer\Utils\DataUtil;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class DataUtilTest extends TestCase
{
    public function test_make_request_data_creates_request_data_when_message_has_method_and_id(): void
    {
        $clientId = 'test_client';
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test.method',
            'id' => 1,
            'params' => ['param1' => 'value1']
        ];

        $result = DataUtil::makeRequestData($clientId, $message);

        $this->assertInstanceOf(RequestData::class, $result);
        $this->assertEquals('test.method', $result->method);
        $this->assertEquals('2.0', $result->jsonRpc);
        $this->assertEquals(1, $result->id);
        $this->assertEquals(['param1' => 'value1'], $result->params);
    }
}
```

## Additional Development Information

### Code Style

- The project follows PSR-12 coding standards.
- Use type hints for method parameters and return types.
- Document classes and methods with PHPDoc comments.

### Creating MCP Tools

1. Use the provided command to generate a new tool:
   ```bash
   php bin/console make:mcp-tool MyCustomTool
   ```

2. Implement the `StreamableToolInterface` in your custom tool class:
   ```php
   use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

   class MyCustomTool implements StreamableToolInterface
   {
       // Implementation
   }
   ```

3. Register your tool in `config/packages/klp_mcp_server.yaml`.

### Testing MCP Tools

Use the provided command to test your MCP tools:

```bash
# Test a specific tool interactively
php bin/console mcp:test-tool MyCustomTool

# List all available tools
php bin/console mcp:test-tool --list

# Test with specific JSON input
php bin/console mcp:test-tool MyCustomTool --input='{"param":"value"}'
```

### Debugging

- Enable Symfony's debug mode for detailed error messages.
- Use the MCP Inspector for visualizing and testing your MCP tools:
  ```bash
  npx @modelcontextprotocol/inspector node build/index.js
  ```

### Architecture Notes

- The package implements a publish/subscribe (pub/sub) messaging pattern through its adapter system.
- The default Redis adapter maintains message queues for each client, identified by unique client IDs.
- The bundle supports two transport protocols:
  - **SSE (Server-Sent Events)**: Long-lived connections that subscribe to messages for their respective clients and deliver them in real-time.
  - **StreamableHTTP**: An HTTP-based protocol that can handle both streaming and non-streaming responses.
