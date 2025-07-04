<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\ResourceService\Resource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceTemplateInterface;
use KLP\KlpMcpServer\Services\ResourceService\SamplingAwareResourceInterface;
use Symfony\Component\Finder\Finder;

/**
 * Dynamic Analysis Resource - Provides AI-powered analysis of different aspects of a Symfony project
 *
 * This resource template can analyze:
 * - Controllers: analysis://controller/{controllerName}
 * - Services: analysis://service/{serviceName}
 * - Entities: analysis://entity/{entityName}
 * - Bundles: analysis://bundle/{bundleName}
 *
 * Each analysis uses the sampling client to generate intelligent insights about the requested component.
 */
class DynamicAnalysisResource implements ResourceTemplateInterface, SamplingAwareResourceInterface
{
    private SamplingClient|null $samplingClient = null;
    private string $projectRoot;

    /**
     * Supported analysis types and their patterns
     */
    private const ANALYSIS_PATTERNS = [
        'controller' => '/^analysis:\/\/controller\/(.+)$/',
        'service' => '/^analysis:\/\/service\/(.+)$/',
        'entity' => '/^analysis:\/\/entity\/(.+)$/',
        'bundle' => '/^analysis:\/\/bundle\/(.+)$/',
    ];

    public function __construct(string|null $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? getcwd() . '/../';
    }

    public function getName(): string
    {
        return 'Symfony Component Analysis';
    }

    public function getDescription(): string
    {
        return 'AI-powered analysis of Symfony project components (controllers, services, entities, bundles)';
    }

    public function getMimeType(): string
    {
        return 'text/markdown';
    }

    public function getUriTemplate(): string
    {
        return 'analysis://{type}/{name}';
    }

    public function setSamplingClient(SamplingClient $samplingClient): void
    {
        $this->samplingClient = $samplingClient;
    }

    public function getUri(): string
    {
        // This is called on the template itself, return a generic URI
        return 'analysis://';
    }

    public function getData(): string
    {
        // This is called on the template itself, return available analysis types
        return $this->getAvailableAnalysesMarkdown();
    }

    public function getSize(): int
    {
        return strlen($this->getData());
    }

    public function resourceExists(string $uri): bool
    {
        foreach (self::ANALYSIS_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $uri, $matches)) {
                $componentName = $matches[1];
                return $this->componentExists($type, $componentName);
            }
        }
        return false;
    }

    public function getResource(string $uri): ResourceInterface|null
    {
        foreach (self::ANALYSIS_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $uri, $matches)) {
                $componentName = $matches[1];

                if (!$this->componentExists($type, $componentName)) {
                    return null;
                }

                // Return a new resource instance for this specific analysis
                return new class($uri, $type, $componentName, $this->samplingClient, $this->projectRoot) implements ResourceInterface, SamplingAwareResourceInterface {
                    private string $uri;
                    private string $type;
                    private string $componentName;
                    private SamplingClient|null $samplingClient;
                    private string $projectRoot;
                    private string|null $cachedData = null;

                    public function __construct(
                        string $uri,
                        string $type,
                        string $componentName,
                        SamplingClient|null $samplingClient,
                        string $projectRoot
                    ) {
                        $this->uri = $uri;
                        $this->type = $type;
                        $this->componentName = $componentName;
                        $this->samplingClient = $samplingClient;
                        $this->projectRoot = $projectRoot;
                    }

                    public function getUri(): string
                    {
                        return $this->uri;
                    }

                    public function getName(): string
                    {
                        return sprintf('%s Analysis: %s', ucfirst($this->type), $this->componentName);
                    }

                    public function getDescription(): string
                    {
                        return sprintf('AI-powered analysis of %s "%s"', $this->type, $this->componentName);
                    }

                    public function getMimeType(): string
                    {
                        return 'text/markdown';
                    }

                    public function setSamplingClient(SamplingClient $samplingClient): void
                    {
                        $this->samplingClient = $samplingClient;
                    }

                    public function getData(): string
                    {
                        if ($this->cachedData !== null) {
                            return $this->cachedData;
                        }

                        $componentCode = $this->getComponentCode();

                        if ($this->samplingClient === null || empty($componentCode)) {
                            $this->cachedData = $this->getFallbackAnalysis();
                            return $this->cachedData;
                        }

                        try {
                            $prompt = $this->buildAnalysisPrompt($componentCode);
                            $systemPrompt = 'You are a Symfony expert providing detailed code analysis. Focus on architecture, best practices, potential improvements, and security considerations.';

                            $response = $this->samplingClient->createTextRequest(
                                $prompt,
                                null, // ModelPreferences
                                $systemPrompt,
                                2000  // maxTokens
                            );

                            $this->cachedData = $response->getContent()->getText() ?? $this->getFallbackAnalysis();
                        } catch (\Exception $e) {
                            $this->cachedData = $this->getFallbackAnalysis() . "\n\n**Error during analysis:** " . $e->getMessage();
                        }

                        return $this->cachedData;
                    }

                    public function getSize(): int
                    {
                        return strlen($this->getData());
                    }

                    private function getComponentCode(): string
                    {
                        $finder = new Finder();

                        switch ($this->type) {
                            case 'controller':
                                $pattern = sprintf('*%s*.php', str_replace('\\', '/', $this->componentName));
                                $finder->files()->in($this->projectRoot . '/src/Controller')->name($pattern);
                                break;

                            case 'service':
                                $pattern = sprintf('*%s*.php', str_replace('\\', '/', $this->componentName));
                                $finder->files()->in($this->projectRoot . '/src')->name($pattern);
                                break;

                            case 'entity':
                                $pattern = sprintf('%s.php', $this->componentName);
                                $finder->files()->in($this->projectRoot . '/src/Entity')->name($pattern);
                                break;

                            case 'bundle':
                                $pattern = sprintf('*%s*.php', $this->componentName);
                                $finder->files()->in($this->projectRoot . '/src')->name($pattern)->depth('< 3');
                                break;

                            default:
                                return '';
                        }

                        $code = '';
                        foreach ($finder as $file) {
                            $code .= "// File: " . $file->getRelativePathname() . "\n";
                            $code .= $file->getContents() . "\n\n";

                            // Limit code size to prevent token overflow
                            if (strlen($code) > 50000) {
                                $code .= "// ... (additional files truncated)\n";
                                break;
                            }
                        }

                        return $code;
                    }

                    private function buildAnalysisPrompt(string $code): string
                    {
                        $prompts = [
                            'controller' => "Analyze this Symfony controller and provide insights on:\n" .
                                "1. Route organization and RESTful design\n" .
                                "2. Security considerations (authentication, authorization, CSRF)\n" .
                                "3. Performance optimizations\n" .
                                "4. Code quality and Symfony best practices\n" .
                                "5. Potential refactoring opportunities\n\n" .
                                "Controller code:\n```php\n{$code}\n```",

                            'service' => "Analyze this Symfony service and provide insights on:\n" .
                                "1. Service architecture and dependency injection\n" .
                                "2. SOLID principles adherence\n" .
                                "3. Performance and memory considerations\n" .
                                "4. Error handling and logging\n" .
                                "5. Testability improvements\n\n" .
                                "Service code:\n```php\n{$code}\n```",

                            'entity' => "Analyze this Doctrine entity and provide insights on:\n" .
                                "1. Database design and relationships\n" .
                                "2. Validation rules and constraints\n" .
                                "3. Performance considerations (indexes, lazy loading)\n" .
                                "4. Data integrity and business rules\n" .
                                "5. Potential schema improvements\n\n" .
                                "Entity code:\n```php\n{$code}\n```",

                            'bundle' => "Analyze this Symfony bundle structure and provide insights on:\n" .
                                "1. Bundle organization and architecture\n" .
                                "2. Service configuration and dependency injection\n" .
                                "3. Extension points and flexibility\n" .
                                "4. Integration with Symfony ecosystem\n" .
                                "5. Documentation and usability\n\n" .
                                "Bundle code:\n```php\n{$code}\n```"
                        ];

                        return $prompts[$this->type] ?? "Analyze this Symfony component:\n```php\n{$code}\n```";
                    }

                    private function getFallbackAnalysis(): string
                    {
                        return sprintf(
                            "# %s Analysis: %s\n\n" .
                            "**Note:** AI-powered analysis is currently unavailable.\n\n" .
                            "## Component Type\n%s\n\n" .
                            "## Location\n`%s`\n\n" .
                            "## General Recommendations\n" .
                            "- Follow Symfony coding standards\n" .
                            "- Implement proper error handling\n" .
                            "- Add comprehensive PHPDoc comments\n" .
                            "- Write unit and functional tests\n" .
                            "- Consider performance implications\n",
                            ucfirst($this->type),
                            $this->componentName,
                            ucfirst($this->type),
                            $this->uri
                        );
                    }
                };
            }
        }

        return null;
    }

    private function componentExists(string $type, string $componentName): bool
    {
        $finder = new Finder();

        try {
            switch ($type) {
                case 'controller':
                    $pattern = sprintf('*%s*.php', str_replace('\\', '/', $componentName));
                    $finder->files()->in($this->projectRoot . '/src/Controller')->name($pattern);
                    break;

                case 'service':
                    $pattern = sprintf('*%s*.php', str_replace('\\', '/', $componentName));
                    $finder->files()->in($this->projectRoot . '/src')->name($pattern);
                    break;

                case 'entity':
                    $pattern = sprintf('%s.php', $componentName);
                    if (is_dir($this->projectRoot . '/src/Entity')) {
                        $finder->files()->in($this->projectRoot . '/src/Entity')->name($pattern);
                    }
                    break;

                case 'bundle':
                    $pattern = sprintf('*%s*.php', $componentName);
                    $finder->files()->in($this->projectRoot . '/src')->name($pattern)->depth('< 3');
                    break;

                default:
                    return false;
            }

            return $finder->hasResults();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getAvailableAnalysesMarkdown(): string
    {
        $markdown = "# Symfony Component Analysis\n\n";
        $markdown .= "This resource provides AI-powered analysis of various Symfony components.\n\n";
        $markdown .= "## Available Analysis Types\n\n";

        $examples = [
            'controller' => [
                'pattern' => 'analysis://controller/{ControllerName}',
                'example' => 'analysis://controller/UserController',
                'description' => 'Analyzes Symfony controllers for best practices, security, and performance'
            ],
            'service' => [
                'pattern' => 'analysis://service/{ServiceName}',
                'example' => 'analysis://service/UserService',
                'description' => 'Analyzes services for SOLID principles, dependency injection, and testability'
            ],
            'entity' => [
                'pattern' => 'analysis://entity/{EntityName}',
                'example' => 'analysis://entity/User',
                'description' => 'Analyzes Doctrine entities for database design and performance'
            ],
            'bundle' => [
                'pattern' => 'analysis://bundle/{BundleName}',
                'example' => 'analysis://bundle/UserBundle',
                'description' => 'Analyzes bundle structure and configuration'
            ]
        ];

        foreach ($examples as $type => $info) {
            $markdown .= sprintf(
                "### %s Analysis\n" .
                "- **Pattern:** `%s`\n" .
                "- **Example:** `%s`\n" .
                "- **Description:** %s\n\n",
                ucfirst($type),
                $info['pattern'],
                $info['example'],
                $info['description']
            );
        }

        $markdown .= "## Features\n\n";
        $markdown .= "- **AI-Powered:** Uses advanced language models for intelligent code analysis\n";
        $markdown .= "- **Comprehensive:** Covers architecture, security, performance, and best practices\n";
        $markdown .= "- **Actionable:** Provides specific recommendations for improvements\n";
        $markdown .= "- **Cached:** Analysis results are cached to improve performance\n";

        return $markdown;
    }
}
