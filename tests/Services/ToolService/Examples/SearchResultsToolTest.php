<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Examples\SearchResultsTool;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use PHPUnit\Framework\TestCase;

class SearchResultsToolTest extends TestCase
{
    private SearchResultsTool $tool;

    protected function setUp(): void
    {
        $this->tool = new SearchResultsTool;
    }

    public function test_get_name(): void
    {
        $this->assertSame('search_results', $this->tool->getName());
    }

    public function test_get_description(): void
    {
        $this->assertSame('Example tool that returns search results in a structured array format', $this->tool->getDescription());
    }

    public function test_get_input_schema(): void
    {
        $schemaObject = $this->tool->getInputSchema();

        $this->assertInstanceOf(StructuredSchema::class, $schemaObject);

        $schema = $schemaObject->asArray();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertContains('query', $schema['required']);
    }

    public function test_get_input_schema_details(): void
    {
        $schemaObject = $this->tool->getInputSchema();
        $schema = $schemaObject->asArray();

        // Test query property
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertSame('string', $schema['properties']['query']['type']);
        $this->assertSame('Search query to process', $schema['properties']['query']['description']);

        // Test limit property
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertSame('integer', $schema['properties']['limit']['type']);
        $this->assertSame('Maximum number of results to return', $schema['properties']['limit']['description']);
        $this->assertSame('10', $schema['properties']['limit']['default']);

        // Test required fields
        $this->assertSame(['query'], $schema['required']);
    }

    public function test_get_output_schema(): void
    {
        $schemaObject = $this->tool->getOutputSchema();

        $this->assertInstanceOf(StructuredSchema::class, $schemaObject);

        $schema = $schemaObject->asArray();

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('results', $schema['properties']);
        $this->assertArrayHasKey('metadata', $schema['properties']);
        $this->assertArrayHasKey('suggestions', $schema['properties']);
    }

    public function test_get_output_schema_results_array(): void
    {
        $schemaObject = $this->tool->getOutputSchema();
        $schema = $schemaObject->asArray();

        // Test results array structure
        $results = $schema['properties']['results'];
        $this->assertSame('array', $results['type']);
        $this->assertSame('Array of search results', $results['description']);
        $this->assertArrayHasKey('items', $results);

        // Test results items object structure
        $items = $results['items'];
        $this->assertSame('object', $items['type']);
        $this->assertArrayHasKey('properties', $items);

        // Test result object properties
        $properties = $items['properties'];
        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('url', $properties);
        $this->assertArrayHasKey('snippet', $properties);
        $this->assertArrayHasKey('score', $properties);

        $this->assertSame('string', $properties['id']['type']);
        $this->assertSame('string', $properties['title']['type']);
        $this->assertSame('string', $properties['url']['type']);
        $this->assertSame('string', $properties['snippet']['type']);
        $this->assertSame('number', $properties['score']['type']);

        // Test required fields in result objects
        $this->assertContains('id', $items['required']);
        $this->assertContains('title', $items['required']);
    }

    public function test_get_output_schema_metadata_object(): void
    {
        $schemaObject = $this->tool->getOutputSchema();
        $schema = $schemaObject->asArray();

        // Test metadata object structure
        $metadata = $schema['properties']['metadata'];
        $this->assertSame('object', $metadata['type']);
        $this->assertSame('Search metadata', $metadata['description']);
        $this->assertArrayHasKey('properties', $metadata);

        // Test metadata properties (note: nested object has extra 'properties' level)
        $nestedProperties = $metadata['properties'];
        $this->assertArrayHasKey('properties', $nestedProperties);
        $properties = $nestedProperties['properties'];

        $this->assertArrayHasKey('totalResults', $properties);
        $this->assertArrayHasKey('searchTime', $properties);
        $this->assertArrayHasKey('query', $properties);

        $this->assertSame('integer', $properties['totalResults']['type']);
        $this->assertSame('number', $properties['searchTime']['type']);
        $this->assertSame('string', $properties['query']['type']);

        // Test required fields in metadata
        $this->assertContains('totalResults', $nestedProperties['required']);
        $this->assertContains('query', $nestedProperties['required']);
    }

    public function test_get_output_schema_suggestions_array(): void
    {
        $schemaObject = $this->tool->getOutputSchema();
        $schema = $schemaObject->asArray();

        // Test suggestions array structure
        $suggestions = $schema['properties']['suggestions'];
        $this->assertSame('array', $suggestions['type']);
        $this->assertSame('Alternative search suggestions', $suggestions['description']);
        $this->assertArrayHasKey('items', $suggestions);
        $this->assertSame('string', $suggestions['items']['type']);
    }

    public function test_execute_with_default_limit(): void
    {
        $result = $this->tool->execute(['query' => 'symfony']);

        $this->assertInstanceOf(TextToolResult::class, $result);

        $sanitized = $result->getSanitizedResult();
        $this->assertArrayHasKey('text', $sanitized);

        $data = json_decode($sanitized['text'], true);
        $this->assertIsArray($data);

        // Should return 5 results (max) even with default limit of 10
        $this->assertCount(5, $data['results']);
    }

    public function test_execute_with_custom_limit_within_range(): void
    {
        $result = $this->tool->execute([
            'query' => 'php testing',
            'limit' => 3,
        ]);

        $this->assertInstanceOf(TextToolResult::class, $result);

        $sanitized = $result->getSanitizedResult();
        $data = json_decode($sanitized['text'], true);

        // Should return 3 results as requested
        $this->assertCount(3, $data['results']);
    }

    public function test_execute_with_limit_exceeding_max(): void
    {
        $result = $this->tool->execute([
            'query' => 'unit tests',
            'limit' => 20,
        ]);

        $this->assertInstanceOf(TextToolResult::class, $result);

        $sanitized = $result->getSanitizedResult();
        $data = json_decode($sanitized['text'], true);

        // Should return only 5 results (max limit)
        $this->assertCount(5, $data['results']);
    }

    public function test_execute_result_structure(): void
    {
        $query = 'symfony mcp';
        $result = $this->tool->execute(['query' => $query, 'limit' => 2]);

        $sanitized = $result->getSanitizedResult();
        $data = json_decode($sanitized['text'], true);

        // Verify top-level structure
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('suggestions', $data);

        // Verify results array
        $this->assertIsArray($data['results']);
        $this->assertCount(2, $data['results']);

        // Verify first result structure
        $firstResult = $data['results'][0];
        $this->assertArrayHasKey('id', $firstResult);
        $this->assertArrayHasKey('title', $firstResult);
        $this->assertArrayHasKey('url', $firstResult);
        $this->assertArrayHasKey('snippet', $firstResult);
        $this->assertArrayHasKey('score', $firstResult);

        $this->assertSame('result_1', $firstResult['id']);
        $this->assertStringContainsString($query, $firstResult['title']);
        $this->assertStringStartsWith('https://example.com/result/', $firstResult['url']);
        $this->assertIsString($firstResult['snippet']);
        $this->assertIsFloat($firstResult['score']);

        // Verify metadata
        $this->assertArrayHasKey('totalResults', $data['metadata']);
        $this->assertArrayHasKey('searchTime', $data['metadata']);
        $this->assertArrayHasKey('query', $data['metadata']);

        $this->assertSame(2, $data['metadata']['totalResults']);
        $this->assertSame(0.125, $data['metadata']['searchTime']);
        $this->assertSame($query, $data['metadata']['query']);

        // Verify suggestions
        $this->assertIsArray($data['suggestions']);
        $this->assertCount(3, $data['suggestions']);
        $this->assertStringContainsString($query, $data['suggestions'][0]);
        $this->assertStringContainsString($query, $data['suggestions'][1]);
        $this->assertStringContainsString($query, $data['suggestions'][2]);
    }

    public function test_execute_result_fields_content(): void
    {
        $query = 'test query';
        $result = $this->tool->execute(['query' => $query, 'limit' => 5]);

        $sanitized = $result->getSanitizedResult();
        $data = json_decode($sanitized['text'], true);

        // Verify each result has correct content
        foreach ($data['results'] as $index => $item) {
            $resultNumber = $index + 1;

            $this->assertSame("result_$resultNumber", $item['id']);
            $this->assertSame("Search Result $resultNumber for: $query", $item['title']);
            $this->assertSame("https://example.com/result/$resultNumber", $item['url']);
            $this->assertSame("This is a snippet for result $resultNumber matching your search query.", $item['snippet']);

            // Verify score decreases with each result
            $expectedScore = 1.0 - ($resultNumber * 0.1);
            $this->assertEqualsWithDelta($expectedScore, $item['score'], 0.001);
        }
    }

    public function test_execute_suggestions_format(): void
    {
        $query = 'symfony';
        $result = $this->tool->execute(['query' => $query]);

        $sanitized = $result->getSanitizedResult();
        $data = json_decode($sanitized['text'], true);

        $suggestions = $data['suggestions'];

        $this->assertSame("$query tips", $suggestions[0]);
        $this->assertSame("$query tutorial", $suggestions[1]);
        $this->assertSame("$query examples", $suggestions[2]);
    }

    public function test_execute_with_different_queries(): void
    {
        $queries = ['php', 'unit testing', 'symfony bundle', 'mcp server'];

        foreach ($queries as $query) {
            $result = $this->tool->execute(['query' => $query]);

            $sanitized = $result->getSanitizedResult();
            $data = json_decode($sanitized['text'], true);

            // Verify query is reflected in results
            $this->assertSame($query, $data['metadata']['query']);

            foreach ($data['results'] as $item) {
                $this->assertStringContainsString($query, $item['title']);
            }

            foreach ($data['suggestions'] as $suggestion) {
                $this->assertStringContainsString($query, $suggestion);
            }
        }
    }

    public function test_execute_with_limit_one(): void
    {
        $result = $this->tool->execute([
            'query' => 'single result',
            'limit' => 1,
        ]);

        $sanitized = $result->getSanitizedResult();
        $data = json_decode($sanitized['text'], true);

        $this->assertCount(1, $data['results']);
        $this->assertSame(1, $data['metadata']['totalResults']);
    }

    public function test_execute_returns_valid_json(): void
    {
        $result = $this->tool->execute(['query' => 'json test']);

        $sanitized = $result->getSanitizedResult();

        // Verify it's valid JSON
        $decoded = json_decode($sanitized['text'], true);
        $this->assertIsArray($decoded);
        $this->assertNull(json_last_error() === JSON_ERROR_NONE ? null : json_last_error_msg());
    }

    public function test_get_annotations(): void
    {
        $annotations = $this->tool->getAnnotations();

        $this->assertInstanceOf(ToolAnnotation::class, $annotations);
    }

    public function test_execute_score_decreases_correctly(): void
    {
        $result = $this->tool->execute(['query' => 'score test', 'limit' => 5]);

        $sanitized = $result->getSanitizedResult();
        $data = json_decode($sanitized['text'], true);

        $previousScore = 1.0;
        foreach ($data['results'] as $item) {
            $this->assertLessThanOrEqual($previousScore, $item['score']);
            $previousScore = $item['score'];
        }

        // First result should have highest score
        $this->assertSame(0.9, $data['results'][0]['score']);

        // Last result should have lowest score
        $this->assertSame(0.5, $data['results'][4]['score']);
    }

    public function test_execute_metadata_total_results_matches_array_count(): void
    {
        $limits = [1, 2, 3, 4, 5, 10];

        foreach ($limits as $limit) {
            $result = $this->tool->execute(['query' => 'test', 'limit' => $limit]);

            $sanitized = $result->getSanitizedResult();
            $data = json_decode($sanitized['text'], true);

            $actualCount = count($data['results']);
            $metadataCount = $data['metadata']['totalResults'];

            $this->assertSame($actualCount, $metadataCount);
        }
    }
}
