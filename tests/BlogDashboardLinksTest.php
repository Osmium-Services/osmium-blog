<?php

declare(strict_types=1);

namespace Tests\Services\Blog;

use Osmium\Services\Blog\Models\BlogDashboardLinks;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/BlogDashboardLinks.php';

/**
 * The dashboard tile mirrors the shop's Products tile and opens the blog posts list.
 */
final class BlogDashboardLinksTest extends TestCase
{
    public function test_the_tile_opens_the_blog_posts_list(): void
    {
        $tile = BlogDashboardLinks::quickLink(['osmium' => new \stdClass()]);

        $this->assertSame('blog/', $tile['url']);
        $this->assertSame('Manage Blog Posts', $tile['title']);
        $this->assertSame(['label', 'title', 'icon', 'button', 'url'], \array_keys($tile));
    }
}
