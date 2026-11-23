<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * Answers core's `pages.owners` hook so the admin Pages list can mark the blog's pages "Managed by Blog",
 * link them to the blog editor and keep them from being deleted there.
 */
class BlogOwners
{
    /**
     * @param array{osmium: object} $payload
     * @return array<int, array{label: string, editUrl: string}> keyed by page id
     */
    public static function pages(array $payload): array
    {
        return self::owned($payload['osmium']->dataSource);
    }

    /** @return array<int, array{label: string, editUrl: string}> keyed by page id */
    private static function owned(object $dataSource): array
    {
        $posts = new BlogPost($dataSource);

        $owned = [];
        foreach ($posts->ownedPages() as $row) {
            $owned[(int) $row['page_id']] = ['label' => 'Blog', 'editUrl' => 'blog/edit/?id=' . (int) $row['id']];
        }

        $indexPageId = $posts->indexPageId(BlogSettings::indexSlug());
        if ($indexPageId !== null) $owned[$indexPageId] = ['label' => 'Blog', 'editUrl' => 'blog/'];

        return $owned;
    }

    /**
     * The blog's posts and index page, for the `sitemap.groups` hook: one child sitemap of its own.
     *
     * @param array{dataSource: object} $payload
     * @return array{key: string, label: string, pageIds: array<int, int>, hiddenPageIds: array<int, int>}
     */
    public static function sitemapGroups(array $payload): array
    {
        return [
            'key' => 'blog',
            'label' => 'Blog',
            'pageIds' => \array_keys(self::owned($payload['dataSource'])),
            'hiddenPageIds' => (new BlogPost($payload['dataSource']))->scheduledPageIds(), // Scheduled posts join the sitemap when their time comes
        ];
    }
}
