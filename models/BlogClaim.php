<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * Answers core's `page.claim` hook. Two pages are the blog's: the index (the page at /blog) and a
 * published post. Everything else returns null so another service, or the page's own module folder,
 * gets its turn. The theme supplies the views; the claim only names them and hands over the data.
 *
 * An unpublished post is shown only to a signed-in admin, with $blogIsPreview true (the theme may show a banner).
 *
 * Index view blog/views/index.phtml gets $blogIndex: posts (newest first) and total.
 * Post view blog/views/post.phtml gets $blogPost (the row), $blogAuthor and $blogPostSchema (a ready
 * <script type="application/ld+json"> tag to echo).
 */
class BlogClaim
{
    // One index page lists every post. Paging needs path-based URLs (core drops query strings on public
    // pages, so ?page=2 would be uncrawlable), which a pages row per page cannot give.
    private const INDEX_LIMIT = 500;
    public const INDEX_SLUG = 'blog';

    /**
     * @param array{pageId: int, slug: string, osmium: object, data?: array} $payload
     */
    public static function page(array $payload): ?array
    {
        $posts = new BlogPost($payload['osmium']->dataSource);

        $isIndex = $payload['slug'] === self::INDEX_SLUG;
        if ($isIndex) return self::index($posts);

        $post = $posts->findPublishedByPageId($payload['pageId']);
        $isPreview = false;

        if ($post === null && self::isAdminSession()) {
            $post = $posts->findByPageId($payload['pageId']);
            $isPreview = $post !== null;
        }

        if ($post === null) return null;

        return self::post($post, $payload, $isPreview);
    }

    /**
     * A signed-in, unlocked admin may view a draft or scheduled post on the site; everyone else gets the 404.
     */
    private static function isAdminSession(): bool
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) return false;

        $isSignedIn = !empty($_SESSION['admin_user_id']) && !empty($_SESSION['admin_login_time']);
        $isLocked = !empty($_SESSION['admin_locked']);

        return $isSignedIn && !$isLocked;
    }

    private static function index(BlogPost $posts): array
    {
        $all = $posts->published(limit: self::INDEX_LIMIT);

        return [
            'view' => 'blog/views/index.phtml',
            'data' => ['blogIndex' => ['posts' => $all, 'total' => \count($all)]],
        ];
    }

    private static function post(array $post, array $payload, bool $isPreview): array
    {
        $data = ['blogPost' => $post, 'blogAuthor' => BlogSchema::authorName($post), 'blogIsPreview' => $isPreview];

        // Link previews use the social image, else the hero; $site['socialImage'] is an absolute URL
        $shareImage = $post['social_image'] ?: $post['hero_image'];
        if ($shareImage) $data['site']['socialImage'] = $payload['osmium']->config->site->FQDN . $shareImage;

        $withShareImage = \array_replace_recursive($payload['data'] ?? [], $data);
        $data['blogPostSchema'] = BlogSchema::script($post, $withShareImage);

        return ['view' => 'blog/views/post.phtml', 'data' => $data];
    }
}
