### Version 1.8.2
- **Bug Fixes:**
  - #65 Fix RequestData to accept string|int ID per JSON-RPC 2.0 specification

### Version 1.8.0
- **Compatibility:**
  - Full support for Symfony 8.0
  - Maintains backward compatibility with Symfony 6.4 and 7.4
- **Bug Fixes:**
  - #62 TestMcpToolCommand do not show tools from dynamic providers

### Version 1.7.0
- **Core Features:**
  - Add dynamic resource loading system with ResourceProviderInterface for runtime resource registration
  - Add dynamic prompt loading system with PromptProviderInterface for runtime prompt registration
- **Enhancements:**
  - Improve progress token cleanup with try-finally block to prevent resource leaks when tools throw exceptions
  - Update DataUtil to correctly identify both result and error responses in message parsing
- **Documentation:**
  - Extensive updates to building_prompts.md with PromptProvider implementation guide and examples
  - Extensive updates to building_resources.md with ResourceProvider implementation guide and examples
  - Include basic and advanced examples

### Version 1.6.0
- **Core Features:**
  - Add dynamic tool loading system with ToolProviderInterface
- **Documentation:**
  - Update building_tools.md with step-by-step ToolProvider implementation guide
  - Include basic and advanced examples with database integration and conditional logic
  - Add ExampleToolProvider demonstrating various use cases

### Version 1.5.3
- **Enhancement:**
  - issue a warning on test command when tested tool getInputSchema method returns an array (gentle migration reminder 😃 )
- **Bug Fixes:**
  - make test command compliant with StructuredSchema Tool

### Version 1.5.2 
- **Enhancements:**
  - Add SchemaBuilder class for creating complex JSON Schema structures
  - New helper methods: arrayOfObjects(), arrayOfPrimitives(), nestedObject()
  - Improve schema creation for arrays and nested objects
  - Add comprehensive SchemaBuilder usage documentation in building_tools.md
  - Enhanced SearchResultsTool example demonstrating SchemaBuilder usage
- **Documentation:**
  - Update building_tools.md with SchemaBuilder usage examples
  - Add complete PropertyType enum documentation (STRING, INTEGER, BOOLEAN, NUMBER, ARRAY, OBJECT)
  - Improved schema creation examples for complex tool input/output structures

### Version 1.5.1
- **Enhancements:**
  - Extend PropertyType enum with NUMBER, OBJECT, ARRAY, and BOOLEAN types (#55) by @trandbert37
  - Add support for all JSON schema property types in StructuredSchema system
  - Update MigrateToolSchemaCommand to handle all supported property types
  - Improve ToolParamsValidator with proper validation for number type
  - Enhanced test coverage for all property types

### Version 1.5.0
- **Core Features:**
  - Add MCP Protocol Version 2025-06-18 support
  - Enhanced protocol version handling with automatic version negotiation
  - Improved batch request handling. Batch requests are only supported in protocol version 2025-03-26
- **Enhancements:**
  - Refactor StreamableHttpController with better protocol version detection via headers
  - Add comprehensive validation for batch requests based on the protocol version
  - #49 Add name and title in returned json on resource/call
  - Update the TransportFactory to support multiple protocol versions
- **Architecture Improvements:**
  - #48 Refactor tools to use StructuredSchema for input/output schemas
  - Add comprehensive unit tests
  - Improve code organization with better separation of concerns in request handling
  - Add proper type declarations and final method modifiers where appropriate
- **Bug Fixes:**
  - Improve error handling for invalid protocol versions
- **Documentation:**
  - Add MCP features status table in README
  - Update tool documentation to use StructuredSchema for input/output schemas
- **Migration Command**
  - Add the MigrateToolSchemaCommand to help developers on tools new StructuredSchema feature migration:
  ```php  
  php bin/console mcp:migrate-tool-schema \\App\\MCP\\Tools\\MyLegacyTool
  ```

### Version 1.4.0
- **Core Features:**
  - Add comprehensive sampling support allowing tools to request LLM assistance during execution
  - Introduce SamplingAwareToolInterface for tools that need to make nested LLM calls
  - Add SamplingClient service for managing sampling requests
  - Create example CodeAnalyzerTool demonstrating sampling usage
- **Enhancements:**
  - Automatic injection of SamplingClient into tools that implement SamplingAwareToolInterface
  - Support for text and multi-message sampling requests
  - Model preferences for controlling LLM selection (cost, speed, intelligence priorities)
- **Bug Fixes:**
  - #45 Rename "arguments" option to "inputs" in TestMcpPromptCommand and related files for consistency
- **Documentation:**
  - Add comprehensive sampling documentation with examples and best practices

### Version 1.3.3
- **Bug Fixes:**
  - Protocol version negotiation incorrect (#41) by @Wolfgang-check24

### Version 1.3.2
- **Bug Fixes:**
  - Fix protocol evaluation on client request (#38) by @Wolfgang-check24
  - Fix SSE stream blocking issue (#36) by @Wolfgang-check24
- **Enhancements:**
  - Add test environment initialization and conditional output handling adjustments
- **Documentation:**
  - Move development guidelines to CONTRIBUTING.md

### Version 1.3.1
- **Enhancements:**
  - Add Symfony 6.4 compliancy

### Version 1.3.0

- **Core Features:**
  - Add comprehensive prompt system with multiple message types (Text, Image, Audio, Resource, Collection)
  - Introduce MakeMcpPromptCommand for generating prompts via console
  - Add TestMcpPromptCommand for testing prompt functionality
  - Store client sampling capabilities for better client-server communication
  - Add ProfileGeneratorTool and CollectionToolResult examples
- **Enhancements:**
   - Update documentation with prompts usage and testing commands
- **Bug Fixes:**
  - Remove unused `setAccessible` calls in tests
  - Fix code styling issues
         
### Version 1.2.0

- **Core Features:**
  - Add real-time communication support through Streamable Http
  - Add Streaming Tool Support
  - Add TextToolResult, AudioToolResult, ImageToolResult and ResourceToolResult to properly manage tool returns
  - Both SSE and StreamableHttp can be active that makes clients able to use the old protocol version
- **Deprecations:**
  - Configuration key `klp_mcp_server.server_provider` is replaced by `klp_mcp_server.server_providers` to maintain backward compatibility
  for clients that does not support the `2025-03-26` protocol version yet.
  - The ToolInterface is deprecated. Use StreamableToolInterface instead.

### Version 1.1.0

- **Core Features:**
  - **Resources Management:** You can now serve Resources and Resources Templates
- **Documentation:**
  - Complete documentation about how to implement your Resources

### Version 1.0.0

- **Documentation:**
  - Setup, usage and development instructions.
- **SDK:**
  - A Docker quick setup for those who want to be involved

### Version 0.9.0

- **Core Features:**
  - **New Adapter**: Symfony Cache adpater for Pub/Sub messaging pattern
  - **Refactoring:** Refactor `TestMcpToolCommand` to reduce technical debt and improve code maintainability.
  - **Testing Enhancements:** Enhance test coverage to achieve an acceptable and robust ratio, ensuring reliability and stability.


### V0.8.0
- **Initial Release:**
  Basic implementation of the Model Context Protocol (MCP) server using Server-Sent Events (SSE).
- **Core Features:**
  - Real-time communication support through SSE.
  - Basic tool implementation compliant with MCP specifications.
  - Redis adapter for Pub/Sub messaging pattern.
- **Documentation:** Basic setup and usage instructions.
