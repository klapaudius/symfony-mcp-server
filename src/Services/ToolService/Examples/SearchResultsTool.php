<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\BaseToolInterface;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaBuilder;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;

/**
 * Example tool demonstrating how to create outputSchema with arrays of objects.
 *
 * This tool shows how to use the enhanced SchemaProperty and SchemaBuilder
 * to create complex JSON Schema structures that include arrays of objects.
 */
class SearchResultsTool implements BaseToolInterface
{
    public function getName(): string
    {
        return 'search_results';
    }

    public function getDescription(): string
    {
        return 'Example tool that returns search results in a structured array format';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Search query to process',
                required: true
            ),
            new SchemaProperty(
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Maximum number of results to return',
                default: '10'
            )
        );
    }

    public function getOutputSchema(): ?StructuredSchema
    {
        return new StructuredSchema(
            // Using SchemaBuilder for array of objects
            SchemaBuilder::arrayOfObjects(
                'results',
                [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'snippet' => ['type' => 'string'],
                    'score' => ['type' => 'number']
                ],
                ['id', 'title'],  // id and title are required
                'Array of search results'
            ),

            // Additional metadata object
            SchemaBuilder::nestedObject(
                'metadata',
                [
                    'totalResults' => ['type' => 'integer'],
                    'searchTime' => ['type' => 'number'],
                    'query' => ['type' => 'string']
                ],
                ['totalResults', 'query'],
                'Search metadata'
            ),

            // Array of suggestion strings
            SchemaBuilder::arrayOfPrimitives(
                'suggestions',
                'string',
                'Alternative search suggestions'
            )
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $query = $arguments['query'];
        $limit = $arguments['limit'] ?? 10;

        // Simulate search results
        $results = [];
        for ($i = 1; $i <= min($limit, 5); $i++) {
            $results[] = [
                'id' => "result_$i",
                'title' => "Search Result $i for: $query",
                'url' => "https://example.com/result/$i",
                'snippet' => "This is a snippet for result $i matching your search query.",
                'score' => 1.0 - ($i * 0.1)
            ];
        }

        $response = [
            'results' => $results,
            'metadata' => [
                'totalResults' => count($results),
                'searchTime' => 0.125,
                'query' => $query
            ],
            'suggestions' => [
                "$query tips",
                "$query tutorial",
                "$query examples"
            ]
        ];

        return new TextToolResult(json_encode($response, JSON_PRETTY_PRINT));
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation();
    }
}
