# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony bundle that enables developers to seamlessly add a Model Context Protocol (MCP) server to their Symfony applications. The main goal is to provide a production-ready implementation following the official MCP specifications at https://modelcontextprotocol.io/specification/2025-03-26.

The bundle allows Symfony applications to expose tools, resources, and prompts that can be consumed by Large Language Models (LLMs) through secure transport protocols (SSE and StreamableHTTP), avoiding the security risks of STDIO transport in enterprise environments.

## Commands

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run a specific test file
vendor/bin/phpunit tests/path/to/TestFile.php

# Generate code coverage
vendor/bin/phpunit --coverage-html build/coverage

# Run PHPStan static analysis
vendor/bin/phpstan analyse
```

### Development Tools
```bash
# Create a new MCP tool
php bin/console make:mcp-tool MyCustomTool

# Test a specific MCP tool
php bin/console mcp:test-tool MyCustomTool

# Test tool with specific input
php bin/console mcp:test-tool MyCustomTool --input='{"param":"value"}'

# List all available tools
php bin/console mcp:test-tool --list
```

### MCP Inspector
```bash
# Run the MCP Inspector for visual testing
npx @modelcontextprotocol/inspector node build/index.js
```

## Architecture Overview

### Package Structure
This is a Symfony bundle that implements the Model Context Protocol (MCP) server. It provides:

- **Dual Transport Support**: Both SSE (Server-Sent Events) and StreamableHTTP protocols
- **Tool System**: Extensible framework for creating MCP tools that LLMs can invoke
- **Resource System**: Management of resources that can be exposed to LLM clients
- **Prompt System**: Pre-defined conversation starters and templates for LLM interactions
- **Progress Notifications**: Real-time progress updates for long-running operations

### Key Components

#### Transport Layer
- **SSE Transport**: Uses Server-Sent Events with a pub/sub pattern via adapters (Cache or Redis)
- **StreamableHTTP Transport**: Direct HTTP-based communication supporting both streaming and non-streaming responses
- **Adapter System**: Abstraction layer allowing different message brokers (Cache, Redis)

#### Tool System
- **BaseToolInterface**: Core interface for all tools (deprecated in favor of StreamableToolInterface)
- **StreamableToolInterface**: Modern interface supporting progress notifications
- **ToolResultInterface**: Abstraction for different result types (Text, Image, Audio, Resource)
- **ProgressNotifier**: System for sending real-time progress updates during tool execution
- **ToolProviderInterface**: Interface for dynamically providing tools based on custom logic (see Dynamic Tool Loading below)

#### Prompt System
- **PromptInterface**: Core interface for creating prompts
- **PromptMessageInterface**: Base interface for different message types
- **Message Types**: TextPromptMessage, ImagePromptMessage, AudioPromptMessage, ResourcePromptMessage
- **CollectionPromptMessage**: Container for multiple prompt messages
- **PromptRepository**: Manages and retrieves available prompts

#### Request Handlers
- Located in `src/Server/Request/`: Handle different MCP protocol methods
- Each handler implements specific MCP operations (initialize, tools/list, tools/call, resources/list, resources/read, prompts/list, prompts/get, etc.)

#### Protocol Implementation
- **MCPProtocol**: Core protocol implementation handling request/response flow
- **RequestHandler/NotificationHandler**: Process different message types
- **MCPServer**: Main server orchestrating all components

### Configuration
- Main config: `config/packages/klp_mcp_server.yaml`
- Tools, resources, and prompts are registered in configuration
- Supports multiple adapters for SSE transport

## Important Notes

- **Cannot use** `symfony server:start` - requires proper web server (Nginx/Apache + PHP-FPM) for concurrent connections
- Tools should implement `StreamableToolInterface` for modern features
- Progress notifications only work with streaming tools (`isStreaming()` returns true)
- All types should use `string|null` syntax instead of `?string` (null at the end)
- Follow PSR-12 coding standards and include PHPDoc for all public methods
