<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * What the blog service adds to the dashboard's quick links tile row (dashboard.quick_links).
 */
class BlogDashboardLinks
{
    /**
     * @param array{osmium: object} $payload
     * @return array<string, string> the Manage Blog Posts tile
     */
    public static function quickLink(array $payload): array
    {
        return [
            'label' => 'Blog',
            'title' => 'Manage Blog Posts',
            'icon' => 'bx-news',
            'button' => 'View Blog Posts',
            'url' => 'blog/',
        ];
    }
}
