<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

use Osmium\Core\Models\Model;
use Osmium\Modules\Admin\Services\AdminPages;

/**
 * Creates a new draft blog post, shared by the MCP tool. The admin editor keeps its own save, but takes its slug
 * clean-up and the blog index page from here so both agree. A draft is noindex and has no published date, so it
 * stays out of the sitemap and off the public blog until someone publishes it in the admin.
 */
class BlogDraft
{
    private const URL_PREFIX = 'blog/';

    /** @param \Closure(string, int, string): void $log called with the action ('Created'), post id and post title */
    public function __construct(
        private BlogPost $posts,
        private AdminPages $pages,
        private \Closure $log,
    ) {}

    /**
     * The body is plain text: BlogText turns it into escaped paragraphs, so nothing typed here can add markup.
     *
     * @return array{id: int, title: string, address: string} the new draft
     * @throws \InvalidArgumentException when the title or slug is empty, or the address is already taken
     */
    public function create(string $title, string $body, string $slug = '', string $excerpt = '', string $description = ''): array
    {
        $title = \trim($title);
        if ($title === '') throw new \InvalidArgumentException('Title is required');

        $slug = self::cleanSlug($slug !== '' ? $slug : $title);
        if ($slug === '') throw new \InvalidArgumentException('Slug is required');

        try {
            $pageId = $this->pages->create([
                'url' => self::URL_PREFIX . $slug,
                'title' => $title,
                'description' => \trim($description),
                'robots' => 'noindex,nofollow',
                'parent_page_id' => $this->indexPageId(),
            ]);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }

        $excerpt = \trim($excerpt) ?: null;
        $id = $this->posts->create($pageId, $excerpt, BlogText::paragraphs($body), null, null, null, false, null);
        ($this->log)('Created', $id, $title);

        return ['id' => $id, 'title' => $title, 'address' => '/' . self::URL_PREFIX . $slug];
    }

    public static function cleanSlug(string $text): string
    {
        $slug = \strtolower(\trim($text));
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return \trim($slug, '-');
    }

    /**
     * The blog index page (/blog), created the first time a post is saved so posts have breadcrumbs
     * to lead back to and the index has an address of its own
     */
    public function indexPageId(): ?int
    {
        foreach (Model::pagesById() as $page) {
            $isBlogIndex = ($page['url'] ?? '') === BlogClaim::INDEX_SLUG && ($page['deleted_at'] ?? null) === null;
            if ($isBlogIndex) return (int) $page['id'];
        }

        return $this->pages->create([
            'url' => BlogClaim::INDEX_SLUG,
            'title' => 'Blog',
            'description' => '',
            'robots' => 'index,follow',
        ]);
    }
}
