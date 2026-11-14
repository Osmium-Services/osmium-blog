<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * What the blog tells the front-end staff bar: which blog page is on screen (adminbar.context) and the admin link
 * that goes with it (adminbar.links). Both read-only and cheap. The page's data is already there from page.claim:
 * $blogPost on a post (a draft preview included), $blogIndex on the blog's index page.
 */
class BlogAdminBar
{
    /**
     * @param array{data?: array<string, mixed>} $payload the finished page's view data
     * @return string|null "blog-post:12" on a post, "blog-index:all" on the index, null elsewhere
     */
    public static function context(array $payload): ?string
    {
        $data = $payload['data'] ?? [];

        $postId = (int)($data['blogPost']['id'] ?? 0);
        $isPost = $postId > 0;
        if ($isPost) return "blog-post:{$postId}";

        $isIndex = isset($data['blogIndex']);

        return $isIndex ? 'blog-index:all' : null;
    }

    /**
     * @param array{context?: string, role?: string} $payload
     * @return array<int, array<string, mixed>>|null
     */
    public static function links(array $payload): ?array
    {
        $context = (string)($payload['context'] ?? '');

        $isPost = \preg_match('/^blog-post:(\d+)$/', $context, $match) === 1;
        if ($isPost) {
            return [['label' => 'Edit post', 'icon' => 'bx-news', 'path' => 'blog/edit/?id=' . (int)$match[1], 'primary' => true]];
        }

        $isIndex = $context === 'blog-index:all';
        if ($isIndex) return [['label' => 'Edit blog posts', 'icon' => 'bx-news', 'path' => 'blog/', 'primary' => true]];

        return null;
    }
}
