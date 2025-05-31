<?php

namespace KLP\KlpMcpServer\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\ResourceService\Resource;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceTemplateInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

class McpDocumentationResource implements ResourceTemplateInterface
{
    private string $baseDir;

    private array $filenames = [];

    public function __construct(KernelInterface $kernel)
    {
        $this->baseDir = $kernel->getProjectDir().'/vendor/klapaudius/symfony-mcp-server/docs';
    }

    public function getUriTemplate(): string
    {
        return "file://klp/docs/{filename}.md";
    }

    public function getName(): string
    {
        return "documentation.md";
    }

    public function getDescription(): string
    {
        $finder = new Finder;
        $finder->files()->in($this->baseDir)->name('*.md');

        $filenames = [];
        foreach ($finder as $file) {
            $filenames[] = rtrim($file->getFilename(), '.md');
        }

        return "The MCP Documentation resources. Filename can be one of '".implode("', '", $filenames)."'.";
    }

    public function getMimeType(): string
    {
        return "text/plain";
    }

    /**
     * Retrieve a resource by its URI.
     *
     * @param string $uri The unique identifier of the resource.
     * @return ResourceInterface|null The retrieved resource object, or null if the resource does not exist.
     */
    public function getResource(string $uri): ?ResourceInterface
    {
        if (! $this->resourceExists($uri)) {
            return null;
        }
        $filename = $this->getFilenameFromUri($uri);
        $path = $this->baseDir.'/'.$filename.'.md';

        $data = file_get_contents($path);

        return new Resource(
            $uri,
            "$filename.md",
            explode("\n", $data)[0],
            $this->guessMimeType($path),
            $data
        );
    }

    /**
     * Check if the specified resource exists.
     *
     * @param string $uri The URI of the resource to check.
     * @return bool True if the resource exists, false otherwise.
     */
    public function resourceExists(string $uri): bool
    {
        $filename = $this->getFilenameFromUri($uri);

        return file_exists($this->baseDir.'/'.$filename.'.md');
    }

    /**
     * Extracts the filename from a given URI.
     *
     * @param string $uri The URI from which to extract the filename.
     * @return string|null The extracted filename if found, or null otherwise.
     */
    private function getFilenameFromUri(string $uri): ?string
    {
        if (!isset($this->filenames[$uri])) {
            if (!preg_match('#^file://klp/docs/([^/]+)\.md$#', $uri, $matches)) {
                $this->filenames[$uri] = null;
            } else {
                $this->filenames[$uri] = $matches[1];
            }
        }

        return $this->filenames[$uri];
    }

    /**
     * Guess the MIME type of a file.
     *
     * @param string $path The path to the file.
     * @return string The guessed MIME type.
     */
    protected function guessMimeType(string $path): string
    {
        $mimeType = mime_content_type($path);
        return $mimeType ?: 'application/octet-stream';
    }
}
