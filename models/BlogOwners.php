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
        $posts = new BlogPost($payload['osmium']->dataSource);

        $owned = [];
        foreach ($posts->ownedPages() as $row) {
            $owned[(int) $row['page_id']] = ['label' => 'Blog', 'editUrl' => 'blog/edit/?id=' . (int) $row['id']];
        }

        $indexPageId = $posts->indexPageId(BlogClaim::INDEX_SLUG);
        if ($indexPageId !== null) $owned[$indexPageId] = ['label' => 'Blog', 'editUrl' => 'blog/'];

        return $owned;
    }
}
