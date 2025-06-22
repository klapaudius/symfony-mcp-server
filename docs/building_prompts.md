# Building MCP Prompts - Complete Walkthrough

This guide provides a comprehensive walkthrough for creating MCP prompts in the Symfony MCP Server bundle, using content creation as our example domain.

## Table of Contents
1. [Understanding MCP Prompts](#understanding-mcp-prompts)
2. [Quick Start with make:mcp-prompt](#quick-start-with-makemcp-prompt)
3. [Basic Prompt Implementation](#basic-prompt-implementation)
4. [Advanced Prompt Features](#advanced-prompt-features)
5. [Multi-Modal Prompts](#multi-modal-prompts)
6. [Dynamic Prompts with Arguments](#dynamic-prompts-with-arguments)
7. [Best Practices](#best-practices)

## Understanding MCP Prompts

MCP prompts are pre-defined conversation starters that help LLMs understand specific tasks or contexts. They provide structured ways to initiate interactions with your Symfony application.

### Key Components

- **PromptInterface**: Core interface all prompts must implement
- **PromptMessageInterface**: Base interface for different message types
- **Message Types**: Text, Image, Audio, Resource, and Collection messages
- **Arguments**: Dynamic parameters that customize prompt behavior

## Quick Start with make:mcp-prompt

The fastest way to create a new MCP prompt is using the built-in generator command. This command creates a prompt class with all the necessary boilerplate code and optionally registers it in your configuration.

### Generate a New Prompt

```bash
# Generate a prompt with a specific name
php bin/console make:mcp-prompt ContentGenerator

# Generate a prompt and let the command prompt you for the name
php bin/console make:mcp-prompt

# Generate without automatic registration
php bin/console make:mcp-prompt BlogHelper
# Choose 'no' when asked about automatic registration
```

### What the Command Does

The `make:mcp-prompt` command will:

1. **Create the prompt class** in `src/MCP/Prompts/`
2. **Generate proper namespace** and class structure
3. **Add all required methods** with basic implementations
4. **Create a kebab-case prompt name** automatically
5. **Optionally register** the prompt in `config/packages/klp_mcp_server.yaml`

### Customizing the Generated Prompt

After generation, customize the prompt by:

1. **Update the description** to reflect the actual purpose
2. **Define meaningful arguments** with proper validation
3. **Implement the prompt logic** in `getMessages()`
4. **Add multi-modal content** if needed (images, audio, resources)

### Command Options and Features

The `make:mcp-prompt` command includes several helpful features:

- **Smart naming**: Automatically appends "Prompt" if not provided
- **Kebab-case conversion**: Converts class names to kebab-case for prompt names
- **Input validation**: Handles various input formats (hyphens, underscores, spaces)
- **Automatic registration**: Option to add the prompt to your configuration
- **Error handling**: Prevents creating duplicate prompts

## Basic Prompt Implementation

Let's start with a simple blog post creation prompt.

### Step 1: Create the Prompt Class

```php
<?php

namespace App\MCP\Prompts;

use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;

class BlogPostPrompt implements PromptInterface
{
    public function getName(): string
    {
        return 'create-blog-post';
    }

    public function getDescription(): string
    {
        return 'Generate a blog post with SEO optimization';
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'topic',
                'description' => 'The main topic of the blog post',
                'required' => true,
            ],
            [
                'name' => 'tone',
                'description' => 'Writing tone (professional, casual, technical)',
                'required' => false,
            ],
        ];
    }

    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $topic = $arguments['topic'] ?? 'general topic';
        $tone = $arguments['tone'] ?? 'professional';

        return new CollectionPromptMessage([
            new TextPromptMessage(
                'user',
                "Create a comprehensive blog post about {$topic}. Use a {$tone} tone."
            ),
            new TextPromptMessage(
                'assistant',
                "I'll create a blog post about {$topic} with a {$tone} tone. Let me structure it with an engaging introduction, detailed body sections, and a compelling conclusion."
            ),
            new TextPromptMessage(
                'user',
                'Please include SEO best practices, meta description, and relevant keywords.'
            ),
        ]);
    }
}
```

### Step 2: Register the Prompt

Add to your `config/packages/klp_mcp_server.yaml`:

```yaml
klp_mcp_server:
    prompts:
        blog_post:
            class: App\Mcp\Prompts\BlogPostPrompt
```

## Advanced Prompt Features

### Content Strategy Prompt with Multiple Messages

```php
<?php

namespace App\MCP\Prompts;


use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;

class ContentStrategyPrompt implements PromptInterface
{
    public function getName(): string
    {
        return 'content-strategy';
    }

    public function getDescription(): string
    {
        return 'Develop a comprehensive content strategy for your brand';
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'brand_name',
                'description' => 'Your brand or company name',
                'required' => true,
            ],
            [
                'name' => 'industry',
                'description' => 'Your industry or niche',
                'required' => true,
            ],
            [
                'name' => 'target_audience',
                'description' => 'Primary target audience description',
                'required' => true,
            ],
            [
                'name' => 'goals',
                'description' => 'Content marketing goals (awareness, conversion, engagement)',
                'required' => false,
            ],
        ];
    }

    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $brand = $arguments['brand_name'] ?? 'Brand';
        $industry = $arguments['industry'] ?? 'industry';
        $audience = $arguments['target_audience'] ?? 'general audience';
        $goals = $arguments['goals'] ?? 'brand awareness and engagement';

        return new CollectionPromptMessage([
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                'You are a content strategy expert with deep knowledge of digital marketing and brand storytelling.'
            ),
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                "Analyze the content landscape for {$brand} in the {$industry} industry."
            ),
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                "Define content pillars for {$audience}"
            ),
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                "Create a content calendar template focusing on {$goals}"
            ),
            new TextPromptMessage(
                PromptMessageInterface::ROLE_ASSISTANT,
                "I'll develop a comprehensive content strategy for {$brand}. This will include content pillars, audience personas, distribution channels, and a 90-day content calendar focused on {$goals}."
            ),
        ]);
    }
}
```

## Multi-Modal Prompts

### Social Media Content Prompt with Images

```php
<?php

namespace App\MCP\Prompts;


use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\ImagePromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;

class SocialMediaContentPrompt implements PromptInterface
{
    public function getName(): string
    {
        return 'social-media-content';
    }

    public function getDescription(): string
    {
        return 'Create engaging social media content with visual recommendations';
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'platform',
                'description' => 'Social media platform (instagram, twitter, linkedin)',
                'required' => true,
            ],
            [
                'name' => 'campaign',
                'description' => 'Campaign name or theme',
                'required' => true,
            ],
            [
                'name' => 'brand_guidelines_url',
                'description' => 'URL to brand guidelines image',
                'required' => false,
            ],
        ];
    }

    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $platform = $arguments['platform'] ?? null;
        $campaign = $arguments['campaign'] ?? null;
        $brandGuidelinesUrl = $arguments['brand_guidelines_url'] ?? null;

        $messages = [
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                "Create {$platform} content for our {$campaign} campaign"
            ),
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                'Please follow these brand guidelines for visual consistency'
            ),
        ];

        // Add brand guidelines image if provided
        if ($brandGuidelinesUrl) {
            $messages[] = new ImagePromptMessage(
                base64_encode(file_get_contents($brandGuidelinesUrl)),
                'image/png',
                PromptMessageInterface::ROLE_USER,
            );
        }

        // Platform-specific requirements
        $platformSpecs = $this->getPlatformSpecifications($platform);
        $messages[] = new TextPromptMessage(
            PromptMessageInterface::ROLE_USER,
            $platformSpecs
        );

        $messages[] = new TextPromptMessage(
            PromptMessageInterface::ROLE_ASSISTANT,
            "I'll create engaging {$platform} content for your {$campaign} campaign, including captions, hashtags, and visual recommendations."
        );

        return new CollectionPromptMessage($messages);
    }

    private function getPlatformSpecifications(string $platform): string
    {
        $specs = [
            'instagram' => 'Instagram: Focus on visual storytelling. Optimal image size: 1080x1080px. Caption limit: 2,200 characters. Use 10-30 relevant hashtags.',
            'twitter' => 'Twitter/X: Concise messaging. Character limit: 280. Use 1-2 hashtags. Include engaging visuals or GIFs.',
            'linkedin' => 'LinkedIn: Professional tone. Optimal post length: 150-300 characters. Use 3-5 professional hashtags.',
        ];

        return $specs[$platform] ?? 'General social media best practices apply.';
    }
}
```

## Dynamic Prompts with Arguments

### Video Script Generator with Resources

```php
<?php

namespace App\MCP\Prompts;

use KLP\KlpMcpServer\Services\PromptService\Message\AudioPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;
use KLP\KlpMcpServer\Services\PromptService\Message\ResourcePromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\TextPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\PromptInterface;

class VideoScriptPrompt implements PromptInterface
{
    public function getName(): string
    {
        return 'video-script';
    }

    public function getDescription(): string
    {
        return 'Generate video scripts with timing and visual cues';
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => 'video_type',
                'description' => 'Type of video (tutorial, explainer, promotional)',
                'required' => true,
            ],
            [
                'name' => 'duration',
                'description' => 'Target duration in seconds',
                'required' => true,
            ],
            [
                'name' => 'script_template_resource',
                'description' => 'Resource URI for script template',
                'required' => false,
            ],
            [
                'name' => 'voiceover_sample',
                'description' => 'URL to voiceover sample for tone reference',
                'required' => false,
            ],
        ];
    }

    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $videoType = $arguments['video_type'] ?? 'general';
        $duration = $arguments['duration'] ?? 60;
        $templateResource = $arguments['script_template_resource'] ?? null;
        $voiceoverUrl = $arguments['voiceover_sample'] ?? null;

        $messages = [
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                'You are an expert video scriptwriter who creates engaging, well-paced scripts with clear visual and audio cues.'
            ),
        ];

        // Add template resource if provided
        if ($templateResource) {
            $messages[] = new ResourcePromptMessage(
                $templateResource,
                'text/plain',
                'Use this template structure for the script',
                PromptMessageInterface::ROLE_USER,
            );
        }

        // Add voiceover reference if provided
        if ($voiceoverUrl) {
            $messages[] = new AudioPromptMessage(
                base64_encode(file_get_contents($voiceoverUrl)),
                'audio/mp3',
                PromptMessageInterface::ROLE_USER,
            );
        }

        $messages[] = new TextPromptMessage(
            'user',
            "Create a {$duration}-second {$videoType} video script with:"
            . "\n- Scene descriptions"
            . "\n- Dialogue/narration"
            . "\n- Visual cues and transitions"
            . "\n- Background music suggestions"
            . "\n- Call-to-action placement"
        );

        return new CollectionPromptMessage($messages);
    }
}
```

## Best Practices

### 1. Argument Validation

```php
public function getMessages(array $arguments = []): CollectionPromptMessage
{
    // Validate required arguments
    if (!isset($arguments['topic'])) {
        throw new \InvalidArgumentException('Topic argument is required');
    }

    // Validate argument values
    $allowedTones = ['professional', 'casual', 'technical', 'creative'];
    $tone = $arguments['tone'] ?? 'professional';
    
    if (!in_array($tone, $allowedTones)) {
        throw new \InvalidArgumentException(
            sprintf('Invalid tone. Allowed values: %s', implode(', ', $allowedTones))
        );
    }

    // Continue with prompt generation...
}
```

### 2. Context-Aware Prompts

```php
class AdaptiveContentPrompt implements PromptInterface
{
    private array $industryTemplates = [
        'tech' => 'Focus on innovation, technical accuracy, and future trends',
        'healthcare' => 'Emphasize trust, compliance, and patient outcomes',
        'finance' => 'Highlight security, ROI, and regulatory compliance',
        'retail' => 'Focus on customer experience, trends, and value propositions',
    ];

    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        $industry = $arguments['industry'] ?? 'general';
        $context = $this->industryTemplates[$industry] ?? 'General business context';

        return [
            new TextPromptMessage(
                PromptMessageInterface::ROLE_USER,
                "Industry context: {$context}"
            ),
            // Additional messages...
        ];
    }
}
```

### 3. Reusable Prompt Components

```php
trait SEOPromptTrait
{
    protected function getSEOInstructions(): TextPromptMessage
    {
        return new TextPromptMessage(
            'system',
            'Include SEO best practices: '
            . '1) Target keyword in title and first paragraph, '
            . '2) Meta description 150-160 characters, '
            . '3) Use header tags (H1, H2, H3) for structure, '
            . '4) Include internal and external links, '
            . '5) Optimize for featured snippets'
        );
    }
}

class OptimizedBlogPrompt implements PromptInterface
{
    use SEOPromptTrait;

    public function getMessages(array $arguments = []): CollectionPromptMessage
    {
        return new CollectionPromptMessage([
            // Other messages...
            $this->getSEOInstructions(),
        ]);
    }
}
```

### 4. Error Handling and Fallbacks

```php
public function getMessages(array $arguments = []): CollectionPromptMessage
{
    try {
        // Attempt to load external resources
        $templateContent = $this->loadTemplate($arguments['template_id'] ?? null);
    } catch (\Exception $e) {
        // Fallback to default template
        $templateContent = $this->getDefaultTemplate();
        
        // Log the error for monitoring
        $this->logger->warning('Failed to load template', [
            'template_id' => $arguments['template_id'] ?? 'none',
            'error' => $e->getMessage(),
        ]);
    }

    return new CollectionPromptMessage([
        new TextPromptMessage('system', $templateContent),
        // Continue with prompt...
    ]);
}
```

### 5. Testing Your Prompts

```bash
# Test your prompt with the console command
php bin/console mcp:test-prompt create_blog_post --arguments='{"topic":"AI in Healthcare","tone":"professional"}'

# Test with different argument combinations
php bin/console mcp:test-prompt video_script --arguments='{"video_type":"tutorial","duration":120}'

# List all available prompts
php bin/console mcp:test-prompt --list
```

## Summary

Building effective MCP prompts involves:

1. **Clear Structure**: Implement the PromptInterface with well-defined arguments
2. **Multi-Modal Support**: Leverage different message types for rich interactions
3. **Dynamic Behavior**: Use arguments to customize prompt behavior
4. **Context Awareness**: Adapt prompts based on industry, platform, or use case
5. **Error Handling**: Implement graceful fallbacks for robust operation

Remember to register your prompts in the configuration and test them thoroughly before deployment. Well-designed prompts significantly enhance the LLM's ability to understand and execute tasks within your Symfony application.
