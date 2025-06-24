# Symfony MCP Server Documentation

Welcome to the Symfony MCP Server documentation. This bundle enables developers to seamlessly add a Model Context Protocol (MCP) server to their Symfony applications with support for both SSE and StreamableHTTP transports.

## Quick Start

- [Installation & Configuration](../README.md#installation) - Get started with the bundle
- [Basic Usage](../README.md#basic-usage) - Learn the fundamentals

## Development Guides

### Tools
- [Building Tools](building_tools.md) - Create custom MCP tools that LLMs can invoke
- [Tool Examples & Patterns](building_tools.md#examples) - Common tool implementation patterns

### Prompts  
- [Building Prompts](building_prompts.md) - Create conversation starters and templates for LLM interactions
- [Prompt Message Types](building_prompts.md#message-types) - Text, Image, Audio, and Resource messages

### Resources
- [Building Resources](building_resources.md) - Manage and expose resources to LLM clients
- [Resource Templates](building_resources.md#templates) - Dynamic resource generation

## Development & Testing

- [Development Guidelines](development_guidelines.md) - Setup, coding standards, and best practices
- [Testing Commands](../README.md#testing-mcp-tools) - Test tools and prompts without MCP clients
- [Docker Setup](development_guidelines.md#docker-setup) - Development environment configuration

## Architecture & Advanced Topics

### Core Concepts
- [Transport Layer](../README.md#why-not-stdio) - SSE vs StreamableHTTP vs STDIO
- [Pub/Sub Architecture](../README.md#pubsub-architecture-with-adapters) - Message broker and adapter system
- [Progress Notifications](building_tools.md#progress-notifications) - Real-time updates for long-running operations

### Configuration
- [Configuration Reference](../src/Resources/config/packages/klp_mcp_server.yaml) - Complete configuration options
- [Redis Adapter](../README.md#redis-adapter-configuration-optional) - Alternative to cache adapter

## Testing & Debugging

- [MCP Inspector](../README.md#visualizing-with-inspector) - Visual testing interface
- [Console Commands](../README.md#testing-mcp-tools) - Command-line testing tools
- [PHPUnit Testing](development_guidelines.md#testing) - Unit and integration tests

## API Reference

### Interfaces
- **Tools**: `StreamableToolInterface`, `BaseToolInterface` (deprecated)
- **Prompts**: `PromptInterface`, `PromptMessageInterface`
- **Resources**: `ResourceInterface`, `ResourceTemplateInterface`
- **Results**: `ToolResultInterface` with Text, Image, Audio, Resource types

### Services
- **ProgressNotifier**: Real-time progress updates
- **PromptRepository**: Prompt management and retrieval
- **MCPProtocol**: Core protocol implementation

## Requirements & Compatibility

- **PHP**: >=8.2
- **Symfony**: >=6.4
- **Transport**: SSE requires proper web server (not `symfony server:start`)

## Contributing

- [Development Guidelines](development_guidelines.md) - Setup and contribution standards
- [Issue Tracker](https://github.com/klapaudius/symfony-mcp-server/issues) - Report bugs and request features

---

**Need Help?** Check the [troubleshooting section](development_guidelines.md#troubleshooting) or create an issue on GitHub.
