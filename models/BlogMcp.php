<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

use Osmium\Core\Library\McpToolException;
use Osmium\Modules\Admin\Models\Changelog;
use Osmium\Modules\Admin\Models\Page;
use Osmium\Modules\Admin\Services\AdminPages;

/**
 * The Blog's tools for the /mcp endpoint, offered through core's `mcp.tools` hook: list posts, and soft delete
 * or restore one. Delete and restore reuse BlogBin, so they behave exactly like the admin screen. There is no
 * permanent delete. Nothing here returns author names or other personal data.
 */
class BlogMcp
{
    private const LIST_LIMIT = 50;
    private const RECORD_TYPE = 'blog_post';

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> tool descriptors
     */
    public static function tools(array $payload): array
    {
        $idAndTitle = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'The post id from blog_posts_list'],
                'expected_title' => ['type' => 'string', 'description' => "The post's exact title as listed. The call is refused if it does not match the id"],
            ],
            'required' => ['id', 'expected_title'],
        ];

        return [
            [
                'name' => 'blog_posts_list',
                'description' => 'Blog posts with id, title, address, draft or published and dates (no authors). Up to 50, newest first. Set deleted to true to list the posts in the bin instead.',
                'inputSchema' => ['type' => 'object', 'properties' => ['deleted' => ['type' => 'boolean', 'description' => 'List deleted posts (the bin)']]],
                'handler' => self::class . '::postsList',
            ],
            [
                'name' => 'blog_post_delete',
                'description' => 'Move a blog post to the bin (a soft delete: its page goes to the Pages bin, nothing is lost). Undo with blog_post_restore.',
                'inputSchema' => $idAndTitle,
                'write' => true,
                'handler' => self::class . '::delete',
            ],
            [
                'name' => 'blog_post_restore',
                'description' => 'Bring a deleted blog post back from the bin.',
                'inputSchema' => $idAndTitle,
                'write' => true,
                'handler' => self::class . '::restore',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function postsList(array $call): array
    {
        $posts = new BlogPost($call['dataSource']);
        $showDeleted = ($call['arguments']['deleted'] ?? false) === true;
        $rows = $showDeleted ? $posts->listDeleted() : $posts->listAll();

        return ['total' => \count($rows), 'posts' => self::shapeList(\array_slice($rows, 0, self::LIST_LIMIT))];
    }

    /**
     * @param array<int, array<string, mixed>> $rows from blog-post-list.sql or blog-post-list-deleted.sql
     * @return array<int, array<string, mixed>> no author, only what identifies and dates a post
     */
    public static function shapeList(array $rows): array
    {
        $posts = [];
        foreach ($rows as $row) {
            $posts[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'address' => '/' . $row['url'],
                'status' => (bool) $row['is_published'] ? 'published' : 'draft',
                'published_at' => $row['published_at'],
                'updated_at' => $row['updated_at'],
            ] + (isset($row['deleted_at']) ? ['deleted_at' => $row['deleted_at']] : []);
        }

        return $posts;
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function delete(array $call): array
    {
        [$id, $expectedTitle] = self::idAndTitle($call['arguments'] ?? []);
        $posts = new BlogPost($call['dataSource']);
        self::requireTitle($posts->getById($id), $expectedTitle, $id);

        try {
            $post = self::bin($call, $posts)->delete($id);
        } catch (\Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return ['deleted' => true, 'id' => $id, 'title' => (string) $post['title'], 'undo' => "blog_post_restore with id {$id} and the same title"];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function restore(array $call): array
    {
        [$id, $expectedTitle] = self::idAndTitle($call['arguments'] ?? []);
        $posts = new BlogPost($call['dataSource']);
        self::requireTitle($posts->getDeletedById($id), $expectedTitle, $id);

        try {
            $post = self::bin($call, $posts)->restore($id);
        } catch (\Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return ['restored' => true, 'id' => $id, 'title' => (string) $post['title']];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{0: int, 1: string}
     */
    public static function idAndTitle(array $arguments): array
    {
        $id = $arguments['id'] ?? null;
        $title = $arguments['expected_title'] ?? null;
        $valid = \is_int($id) && $id > 0 && \is_string($title) && \trim($title) !== '';
        if (!$valid) throw new McpToolException('Give the post id (a whole number) and its exact expected_title.');

        return [$id, \trim($title)];
    }

    /**
     * Refuses unless the post exists and its title is exactly the one given, so a slip of the id cannot
     * change the wrong post.
     *
     * @param array<string, mixed>|null $post
     */
    public static function requireTitle(?array $post, string $expectedTitle, int $id): void
    {
        if ($post === null) throw new McpToolException("No matching post with id {$id}. Check blog_posts_list.");
        if (\trim((string) $post['title']) !== $expectedTitle) {
            throw new McpToolException("The title does not match post {$id}. Check blog_posts_list and try again.");
        }
    }

    /** @param array<string, mixed> $call */
    private static function bin(array $call, BlogPost $posts): BlogBin
    {
        $dataSource = $call['dataSource'];
        $osmium = $call['osmium'];
        $changelog = new Changelog($dataSource, 'changelog');
        $changelog->setGetUser(fn(): array => ['username' => $call['actor']]);

        $pages = new AdminPages(
            page: new Page($dataSource, 'pages'),
            urlTrailingSlash: $osmium->config->site->urlTrailingSlash ?? false,
        );
        $log = fn(string $action, int $id, string $title) => $changelog->log(
            description: $action . ': ' . $title,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );

        return new BlogBin($posts, $pages, $log);
    }
}
