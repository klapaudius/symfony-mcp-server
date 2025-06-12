<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifier;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;

interface StreamableToolInterface extends BaseToolInterface
{
    /**
     * Executes the tool with the provided arguments.
     *
     * This method contains the actual implementation logic of the tool. The arguments
     * are validated against the input schema before this method is called.
     *
     * @param array $arguments The input parameters as an associative array, validated
     *                         against the schema defined in getInputSchema().
     * @return ToolResultInterface The result of the tool execution, containing the output data
     *                             formatted according to MCP specification.
     */
    public function execute(array $arguments): ToolResultInterface;

    /**
     * Determines if this tool should return a streaming response.
     *
     * When this method returns true, the execute() method should return
     * a callback function that will be used as the StreamedResponse callback.
     *
     * @return bool True if the tool supports streaming, false otherwise.
     */
    public function isStreaming(): bool;

    /**
     * Sets the progress notifier for streaming operations.
     *
     * This method allows the tool to receive a progress notifier instance that can be used
     * to send progress updates during streaming operations. The notifier enables the tool
     * to communicate progress information to the client during long-running operations.
     *
     * @param ProgressNotifier $progressNotifier The progress notifier instance to use for
     *                                           sending progress updates during streaming.
     */
    public function setProgressNotifier(ProgressNotifier $progressNotifier): void;
}
