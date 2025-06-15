<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\CollectionToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ImageToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Example streaming tool that generates a user profile with text and image.
 *
 * This tool demonstrates how to return multiple result types using CollectionToolResult.
 */
class ProfileGeneratorTool implements StreamableToolInterface
{
    private string $baseDir;

    private ProgressNotifierInterface $progressNotifier;

    public function __construct(KernelInterface $kernel)
    {
        $this->baseDir = $kernel->getProjectDir().'/vendor/klapaudius/symfony-mcp-server/docs';
    }

    public function getName(): string
    {
        return 'profile-generator';
    }

    public function getDescription(): string
    {
        return 'Generates a user profile with text description and avatar image';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'The name of the user',
                ],
                'role' => [
                    'type' => 'string',
                    'description' => 'The role or profession of the user',
                ],
            ],
            'required' => ['name', 'role'],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation;
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $name = $arguments['name'] ?? 'Unknown User';
        $role = $arguments['role'] ?? 'User';

        $collection = new CollectionToolResult;

        // Generate text profile
        $this->progressNotifier->sendProgress(
            progress: 1,
            total: 3,
            message: 'Generating text profile...'
        );
        $profileText = $this->generateProfileText($name, $role);
        $collection->addItem(new TextToolResult($profileText));
        usleep(100000);

        // Avatar image
        $this->progressNotifier->sendProgress(
            progress: 2,
            total: 3,
            message: 'Generating avatar image...'
        );
        $avatarImageData = base64_encode(file_get_contents($this->baseDir.'/assets/avatar_sample.jpg'));
        $collection->addItem(new ImageToolResult($avatarImageData, 'image/jpeg'));
        usleep(400000);
        $this->progressNotifier->sendProgress(
            progress: 3,
            total: 3,
            message: 'Done.'
        );

        return $collection;
    }

    public function isStreaming(): bool
    {
        return true;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        $this->progressNotifier = $progressNotifier;
    }

    /**
     * Generates a text description for the user profile.
     */
    private function generateProfileText(string $name, string $role): string
    {
        $createdAt = date('Y-m-d H:i:s');

        return <<<TEXT
=== User Profile ===
Name: {$name}
Role: {$role}
Profile Created: {$createdAt}

Welcome, {$name}! As a {$role}, you're part of our growing community.
Your profile has been successfully generated with a custom avatar.

Profile ID: {$this->generateProfileId($name)}
Status: Active
TEXT;
    }

    /**
     * Generates a unique profile ID based on the name.
     */
    private function generateProfileId(string $name): string
    {
        return 'PROF-'.strtoupper(substr(md5($name.time()), 0, 8));
    }
}
