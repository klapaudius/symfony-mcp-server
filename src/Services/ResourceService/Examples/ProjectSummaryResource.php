<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\ResourceService\SamplingAwareResourceInterface;
use KLP\KlpMcpServer\Services\SamplingService\ModelPreferences;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

/**
 * Example resource that uses sampling to generate dynamic project summaries.
 *
 * This resource demonstrates how to use the sampling feature to create
 * AI-generated summaries of project files and structure.
 */
class ProjectSummaryResource implements SamplingAwareResourceInterface
{
    private ?SamplingClient $samplingClient = null;

    private string $projectPath;

    private ?string $cachedData = null;

    public function __construct(?string $projectPath = null)
    {
        $this->projectPath = $projectPath ?? getcwd();
    }

    public function getUri(): string
    {
        return 'project://summary.md';
    }

    public function getName(): string
    {
        return 'Project Summary';
    }

    public function getDescription(): string
    {
        return 'AI-generated summary of the current project structure and key files';
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }

    public function getData(): string
    {
        // Return cached data if available
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

        // If no sampling client is available, return a static summary
        if ($this->samplingClient === null || ! $this->samplingClient->canSample()) {
            return $this->getStaticSummary();
        }

        // Generate dynamic summary using LLM
        try {
            $projectInfo = $this->gatherProjectInfo();
            $this->cachedData = $this->generateDynamicSummary($projectInfo);

            return $this->cachedData;
        } catch (\Exception $e) {
            // Fall back to static summary on error
            return $this->getStaticSummary()."\n\n*Note: Dynamic summary generation failed: ".$e->getMessage().'*';
        }
    }

    public function getSize(): int
    {
        return strlen($this->getData());
    }

    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
        // Clear cache when sampling client changes
        $this->cachedData = null;
    }

    private function getStaticSummary(): string
    {
        return <<<MARKDOWN
# Project Summary

This is a Symfony project located at: {$this->projectPath}

## Project Structure

The project follows standard Symfony conventions with the following key directories:
- `src/` - Application source code
- `tests/` - Test suites
- `config/` - Configuration files
- `public/` - Public web root
- `var/` - Cache and logs

## Key Features

This project implements a Model Context Protocol (MCP) server for Symfony applications.

### Available Resources
- Static resources defined in configuration
- Dynamic resources with sampling support

### Available Tools
- Custom tools implementing the MCP protocol
- Sampling-aware tools for AI-enhanced functionality

### Available Prompts
- Pre-defined prompts for common tasks
- Dynamic prompts with context-aware generation

For more detailed information, please check the project documentation.
MARKDOWN;
    }

    private function gatherProjectInfo(): array
    {
        $info = [
            'path' => $this->projectPath,
            'composer' => [],
            'structure' => [],
            'readme' => '',
        ];

        // Read composer.json if available
        $composerPath = $this->projectPath.'/composer.json';
        if (file_exists($composerPath)) {
            $composerData = json_decode(file_get_contents($composerPath), true);
            $info['composer'] = [
                'name' => $composerData['name'] ?? 'Unknown',
                'description' => $composerData['description'] ?? 'No description',
                'type' => $composerData['type'] ?? 'project',
                'keywords' => $composerData['keywords'] ?? [],
                'require' => array_keys($composerData['require'] ?? []),
            ];
        }

        // Get basic directory structure
        $directories = ['src', 'tests', 'config', 'public', 'docs'];
        foreach ($directories as $dir) {
            $dirPath = $this->projectPath.'/'.$dir;
            if (is_dir($dirPath)) {
                $info['structure'][] = $dir;
            }
        }

        // Read README if available
        $readmePaths = ['README.md', 'readme.md', 'README', 'readme'];
        foreach ($readmePaths as $readmePath) {
            $fullPath = $this->projectPath.'/'.$readmePath;
            if (file_exists($fullPath)) {
                $info['readme'] = substr(file_get_contents($fullPath), 0, 1000).'...';
                break;
            }
        }

        return $info;
    }

    private function generateDynamicSummary(array $projectInfo): string
    {
        $prompt = $this->buildSummaryPrompt($projectInfo);

        $response = $this->samplingClient->createTextRequest(
            $prompt,
            new ModelPreferences(
                hints: [['name' => 'claude-3-sonnet']],
                speedPriority: 0.5,
                intelligencePriority: 0.7
            ),
            null,
            3000
        );

        $generatedSummary = $response->getContent()->getText();

        // Add metadata
        return $generatedSummary."\n\n---\n*This summary was generated using AI analysis of the project structure and files.*";
    }

    private function buildSummaryPrompt(array $projectInfo): string
    {
        $prompt = "Generate a comprehensive markdown summary for a Symfony project with the following information:\n\n";

        if (! empty($projectInfo['composer']['name'])) {
            $prompt .= "Project Name: {$projectInfo['composer']['name']}\n";
            $prompt .= "Description: {$projectInfo['composer']['description']}\n";
            $prompt .= "Type: {$projectInfo['composer']['type']}\n";

            if (! empty($projectInfo['composer']['keywords'])) {
                $prompt .= 'Keywords: '.implode(', ', $projectInfo['composer']['keywords'])."\n";
            }

            if (! empty($projectInfo['composer']['require'])) {
                $mainDeps = array_slice($projectInfo['composer']['require'], 0, 10);
                $prompt .= "\nMain Dependencies:\n".implode("\n", array_map(fn ($dep) => "- $dep", $mainDeps))."\n";
            }
        }

        if (! empty($projectInfo['structure'])) {
            $prompt .= "\nProject Structure includes: ".implode(', ', $projectInfo['structure'])."\n";
        }

        if (! empty($projectInfo['readme'])) {
            $prompt .= "\nREADME excerpt:\n```\n{$projectInfo['readme']}\n```\n";
        }

        $prompt .= "\nBased on this information, create a well-structured project summary that includes:\n";
        $prompt .= "1. A brief project overview\n";
        $prompt .= "2. Key features and capabilities (inferred from dependencies and structure)\n";
        $prompt .= "3. Technology stack analysis\n";
        $prompt .= "4. Project architecture insights\n";
        $prompt .= "5. Potential use cases\n\n";
        $prompt .= 'Format the response as clean markdown with appropriate sections and bullet points.';

        return $prompt;
    }
}
