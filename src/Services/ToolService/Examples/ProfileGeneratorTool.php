<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\CollectionToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ImageToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\SamplingAwareToolInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Example streaming tool that generates a user profile with text and image.
 *
 * This tool demonstrates how to return multiple result types using CollectionToolResult.
 */
class ProfileGeneratorTool implements SamplingAwareToolInterface
{
    private string $baseDir;

    private ?ProgressNotifierInterface $progressNotifier = null;

    private SamplingClient $samplingClient;

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
        $this->progressNotifier?->sendProgress(
            progress: 1,
            total: 4,
            message: 'Generating text profile...'
        );
        $profileText = $this->generateProfileText($name, $role);
        $collection->addItem(new TextToolResult($profileText));
        usleep(100000);

        // Avatar image
        $this->progressNotifier?->sendProgress(
            progress: 3,
            total: 4,
            message: 'Generating avatar image...'
        );
        $avatarImageData = base64_encode(file_get_contents($this->baseDir.'/assets/avatar_sample.jpg'));
        $collection->addItem(new ImageToolResult($avatarImageData, 'image/jpeg'));
        usleep(400000);
        $this->progressNotifier?->sendProgress(
            progress: 4,
            total: 4,
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

        $this->progressNotifier?->sendProgress(
            progress: 2,
            total: 4,
            message: 'Generating welcome message...'
        );

        $welcome = 'Welcome to the MCP!'; // Default welcome message

        // Try to use sampling if available
        if ($this->samplingClient !== null && $this->samplingClient->isEnabled()) {
            try {
                $request = $this->samplingClient->createTextRequest(
                    "Generate a quick welcome message for a user with the following details: \n\n".
                    "Name: {$name}\n".
                    "Role: {$role}\n",
                    new ModelPreferences(
                        [['name' => 'claude-3-sonnet']],
                        0.2,
                        0.8,
                        0.2
                    ),
                    null,
                    200
                );
                $welcome = $request->getContent()->getText() ?? 'Welcome to the MCP!';
            } catch (\Exception $e) {
                // If sampling fails, use the default welcome message
                // This handles cases where the client claims sampling support but doesn't implement it
            }
        }

        return <<<TEXT
=== User Profile ===
Name: {$name}
Role: {$role}
Profile Created: {$createdAt}

$welcome

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

    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }
}
