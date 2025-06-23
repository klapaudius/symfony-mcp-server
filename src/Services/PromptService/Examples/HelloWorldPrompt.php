<?php

namespace KLP\KlpMcpServer\Services\PromptService\Examples;

use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;

/**
 * Example prompt implementation demonstrating the MCP prompt system.
 */
class HelloWorldPrompt implements PromptInterface
{
    /**
     * Get the unique name of the prompt.
     */
    public function getName(): string
    {
        return 'hello-world';
    }

    /**
     * Get the human-readable description of what this prompt does.
     */
    public function getDescription(): string
    {
        return 'A simple greeting prompt that can be personalized with a name';
    }

    /**
     * Get the list of arguments this prompt accepts.
     */
    public function getArguments(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'The name to include in the greeting',
                'required' => false,
            ],
        ];
    }

    /**
     * Get the prompt messages with resolved arguments.
     */
    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $name = $arguments['name'] ?? 'World';

        return (new CollectionPromptMessage)
            ->addMessage(
                new TextPromptMessage(
                    PromptMessageInterface::ROLE_USER,
                    "Hello, {$name}! This is an example MCP prompt."
                )
            );
    }
}
