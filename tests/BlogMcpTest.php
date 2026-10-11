<?php

declare(strict_types=1);

namespace Tests\Services\Blog;

use Osmium\Core\Library\McpToolException;
use Osmium\Services\Blog\Models\BlogMcp;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/BlogMcp.php';

/**
 * The Blog's MCP tools, without the database: which are writes, how a list is shaped, the id-and-title guard,
 * and the refusals that happen before anything is touched.
 */
final class BlogMcpTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    private function tools(): array
    {
        $tools = [];
        foreach (BlogMcp::tools([]) as $tool) {
            $tools[$tool['name']] = $tool;
        }

        return $tools;
    }

    public function test_it_offers_a_list_a_draft_a_delete_and_a_restore_and_nothing_else(): void
    {
        $this->assertSame(['blog_posts_list', 'blog_post_delete', 'blog_post_create_draft', 'blog_post_restore'], \array_keys($this->tools()));
    }

    public function test_only_the_list_is_a_read_tool_and_only_delete_is_destructive(): void
    {
        foreach ($this->tools() as $name => $tool) {
            $this->assertSame($name !== 'blog_posts_list', $tool['write'] ?? false, $name);
            $this->assertSame($name === 'blog_post_delete', $tool['destructive'] ?? false, $name);
        }
    }

    public function test_there_is_no_publish_edit_or_permanent_delete_tool(): void
    {
        foreach (\array_keys($this->tools()) as $name) {
            $this->assertDoesNotMatchRegularExpression('/publish|update|edit|permanent|purge/', $name);
        }
    }

    public function test_delete_and_restore_need_an_id_and_a_title_to_match(): void
    {
        foreach (['blog_post_delete', 'blog_post_restore'] as $name) {
            $this->assertSame(['id', 'expected_title'], $this->tools()[$name]['inputSchema']['required']);
        }
    }

    public function test_the_list_has_no_author_and_marks_a_draft_a_published_post_and_the_bin(): void
    {
        $rows = [
            ['id' => '1', 'title' => 'Draft', 'url' => 'blog/draft', 'is_published' => '0', 'published_at' => null, 'updated_at' => '2026-10-01 10:00:00', 'first_name' => 'Sam', 'last_name' => 'Secret', 'author_user_id' => '9'],
            ['id' => '2', 'title' => 'Live', 'url' => 'blog/live', 'is_published' => '1', 'published_at' => '2026-10-02 09:00:00', 'updated_at' => '2026-10-02 09:00:00', 'first_name' => 'Pat', 'last_name' => 'Private'],
            ['id' => '3', 'title' => 'Binned', 'url' => 'blog/binned', 'is_published' => '0', 'published_at' => null, 'updated_at' => null, 'deleted_at' => '2026-10-03 08:00:00'],
        ];

        $list = BlogMcp::shapeList($rows);

        $this->assertSame(['draft', 'published', 'draft'], \array_column($list, 'status'));
        $this->assertSame(['/blog/draft', '/blog/live', '/blog/binned'], \array_column($list, 'address'));
        $this->assertArrayNotHasKey('deleted_at', $list[0]);
        $this->assertSame('2026-10-03 08:00:00', $list[2]['deleted_at']);
        $this->assertStringNotContainsString('Secret', \json_encode($list));
        $this->assertStringNotContainsString('Private', \json_encode($list));
        $this->assertStringNotContainsString('author', \json_encode($list));
    }

    public function test_the_id_and_title_must_be_a_positive_whole_number_and_a_non_blank_string(): void
    {
        $this->assertSame([5, 'Hello'], BlogMcp::idAndTitle(['id' => 5, 'expected_title' => '  Hello ']));
        foreach ([[], ['id' => 5], ['expected_title' => 'x'], ['id' => '5', 'expected_title' => 'x'], ['id' => 0, 'expected_title' => 'x'], ['id' => -1, 'expected_title' => 'x'], ['id' => 5, 'expected_title' => '  '], ['id' => 5.5, 'expected_title' => 'x']] as $arguments) {
            try {
                BlogMcp::idAndTitle($arguments);
                $this->fail('Accepted ' . \json_encode($arguments));
            } catch (McpToolException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_missing_post_or_a_title_that_does_not_match_is_refused(): void
    {
        $this->expectException(McpToolException::class);
        $this->expectExceptionMessage('No matching post');
        BlogMcp::requireTitle(null, 'Hello', 7);
    }

    public function test_a_title_that_does_not_match_the_id_is_refused_and_a_matching_one_passes(): void
    {
        BlogMcp::requireTitle(['title' => ' Hello '], 'Hello', 7);
        $this->addToAssertionCount(1);

        $this->expectException(McpToolException::class);
        $this->expectExceptionMessage('does not match');
        BlogMcp::requireTitle(['title' => 'Something else'], 'Hello', 7);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function badDrafts(): array
    {
        return [
            'no title' => [['title' => '', 'body' => 'Text'], 'title and a body'],
            'no body' => [['title' => 'Title', 'body' => '   '], 'title and a body'],
            'title too long' => [['title' => \str_repeat('t', 201), 'body' => 'Text'], '200 characters'],
            'body too long' => [['title' => 'Title', 'body' => \str_repeat('b', 50001)], '50,000 characters'],
            'description too long' => [['title' => 'Title', 'body' => 'Text', 'description' => \str_repeat('d', 321)], '320 characters'],
        ];
    }

    /**
     * @dataProvider badDrafts
     * @param array<string, mixed> $arguments
     */
    public function test_a_draft_that_breaks_a_rule_is_refused_before_anything_is_created(array $arguments, string $message): void
    {
        $this->expectException(McpToolException::class);
        $this->expectExceptionMessage($message);

        BlogMcp::createDraft(['arguments' => $arguments]);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function limitsThatAreAllowed(): array
    {
        return [
            'title of 200' => [['title' => \str_repeat('t', 200), 'body' => 'Text'], '200 characters'],
            'body of 50,000' => [['title' => 'Title', 'body' => \str_repeat('b', 50000)], '50,000 characters'],
            'description of 320' => [['title' => 'Title', 'body' => 'Text', 'description' => \str_repeat('d', 320)], '320 characters'],
        ];
    }

    /**
     * @dataProvider limitsThatAreAllowed
     * @param array<string, mixed> $arguments
     */
    public function test_a_value_exactly_at_the_limit_is_not_refused_for_its_length(array $arguments, string $limitMessage): void
    {
        try {
            BlogMcp::createDraft(['arguments' => $arguments, 'dataSource' => null]); // Gets past the checks, then fails for lack of a database
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString($limitMessage, $e->getMessage());
        }
        $this->addToAssertionCount(1);
    }
}
