<?php

declare(strict_types=1);

namespace Tests\Services\Blog;

use Osmium\Services\Blog\Models\BlogAdminBar;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/BlogAdminBar.php';

/**
 * The staff bar's blog answers: a post is announced by its id, the index page as a whole, and each gets its link.
 */
final class BlogAdminBarTest extends TestCase
{
    public function test_a_post_page_is_announced_by_its_id(): void
    {
        $context = BlogAdminBar::context(['data' => ['blogPost' => ['id' => 12, 'title' => 'Hello']]]);

        $this->assertSame('blog-post:12', $context);
    }

    public function test_the_index_page_is_announced(): void
    {
        $context = BlogAdminBar::context(['data' => ['blogIndex' => ['posts' => [], 'total' => 0]]]);

        $this->assertSame('blog-index:all', $context);
    }

    public function test_other_pages_announce_nothing(): void
    {
        $this->assertNull(BlogAdminBar::context(['data' => ['page' => ['title' => 'About']]]));
        $this->assertNull(BlogAdminBar::context(['data' => ['blogPost' => ['id' => 0]]]));
        $this->assertNull(BlogAdminBar::context([]));
    }

    public function test_the_post_link_opens_that_posts_editor(): void
    {
        $links = BlogAdminBar::links(['context' => 'blog-post:12']);

        $this->assertSame('blog/edit/?id=12', $links[0]['path']);
        $this->assertSame('Edit post', $links[0]['label']);
        $this->assertSame('bx-news', $links[0]['icon']); // The blog's own icon
        $this->assertTrue($links[0]['primary']);
    }

    public function test_the_index_link_opens_the_blog_list(): void
    {
        $links = BlogAdminBar::links(['context' => 'blog-index:all']);

        $this->assertSame('blog/', $links[0]['path']);
        $this->assertSame('bx-news', $links[0]['icon']);
    }

    public function test_another_services_context_gets_no_link(): void
    {
        $this->assertNull(BlogAdminBar::links(['context' => 'product:5']));
        $this->assertNull(BlogAdminBar::links(['context' => 'blog-post:abc']));
        $this->assertNull(BlogAdminBar::links([]));
    }

    public function test_the_manifest_registers_both_handlers(): void
    {
        $manifest = \json_decode((string)\file_get_contents(__DIR__ . '/../service.json'), true);

        $this->assertContains('Osmium\\Services\\Blog\\Models\\BlogAdminBar::context', $manifest['hooks']['adminbar.context']);
        $this->assertContains('Osmium\\Services\\Blog\\Models\\BlogAdminBar::links', $manifest['hooks']['adminbar.links']);
    }
}
