<?php

declare(strict_types=1);

namespace Tests\Services\Blog;

use Osmium\Core\Library\McpToolException;
use Osmium\Modules\Admin\Models\Page;
use Osmium\Modules\Admin\Services\AdminPages;
use Osmium\Services\Blog\Models\BlogBin;
use Osmium\Services\Blog\Models\BlogDraft;
use Osmium\Services\Blog\Models\BlogMcp;
use Osmium\Services\Blog\Models\BlogPost;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../models/BlogPost.php';
require_once __DIR__ . '/../models/BlogText.php';
require_once __DIR__ . '/../models/BlogClaim.php';
require_once __DIR__ . '/../models/BlogDraft.php';
require_once __DIR__ . '/../models/BlogBin.php';
require_once __DIR__ . '/../models/BlogMcp.php';

/**
 * Creating a draft and moving a post to the bin and back, run against the real tables. The routes file is faked
 * so the real app/config/routes.php is never rewritten. A draft must stay hidden (noindex, unpublished, no date),
 * a failed create must leave no orphan page, and delete and restore must only ever soft delete.
 */
class BlogDraftBinTest extends DatabaseTestCase
{
    private BlogPost $posts;
    private Page $pages;
    private AdminPages $adminPages;
    private string $runTag;
    /** @var array<int, array{0: string, 1: int, 2: string}> */
    private array $log = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->runTag = 'zq' . \substr(\uniqid(), -6);

        $prefix = $this->db->tablePrefix();
        $tableExists = $this->db->prepare("SHOW TABLES LIKE '{$prefix}blog_posts'")->single() !== false;
        if (!$tableExists) $this->markTestSkipped('The Blog tables are not installed');

        $blogIndexKnown = false;
        foreach (\Osmium\Core\Models\Model::pagesById() as $page) {
            if (($page['url'] ?? '') === 'blog' && ($page['deleted_at'] ?? null) === null) $blogIndexKnown = true;
        }
        if (!$blogIndexKnown) $this->markTestSkipped('The routes file has no Blog index page to put drafts under');

        $this->posts = new BlogPost($this->db);
        $this->pages = new Page($this->db, 'pages');
        $this->adminPages = new class ($this->pages) extends AdminPages {
            public function regenerateRoutesFile(): void {}
        };
        $this->log = [];
    }

    private function draft(): BlogDraft
    {
        return new BlogDraft($this->posts, $this->adminPages, function (string $action, int $id, string $title): void {
            $this->log[] = [$action, $id, $title];
        });
    }

    private function bin(): BlogBin
    {
        return new BlogBin($this->posts, $this->adminPages, function (string $action, int $id, string $title): void {
            $this->log[] = [$action, $id, $title];
        });
    }

    /** @return array{id: int, title: string, address: string} */
    private function newDraft(string $suffix, string $body = 'Some text'): array
    {
        return $this->draft()->create("{$this->runTag} {$suffix}", $body);
    }

    /** @return array<string, mixed> */
    private function pageRow(string $url): array
    {
        $prefix = $this->db->tablePrefix();

        return $this->db->prepare("SELECT * FROM {$prefix}pages WHERE url = :url", [':url' => $url])->single();
    }

    private function pageCount(): int
    {
        $prefix = $this->db->tablePrefix();

        return (int) $this->db->prepare("SELECT COUNT(*) AS total FROM {$prefix}pages")->single()['total'];
    }

    /** @return array<string, mixed> */
    private function call(array $arguments): array
    {
        return ['dataSource' => $this->db, 'arguments' => $arguments, 'osmium' => (object) ['config' => (object) ['site' => (object) []]], 'actor' => 'tester'];
    }

    // ---- creating a draft ---------------------------------------------------------------------------------

    public function test_a_draft_is_a_hidden_unpublished_post_under_the_blog_index(): void
    {
        $draft = $this->newDraft('first');
        $slug = \strtolower($this->runTag) . '-first';

        $page = $this->pageRow("blog/{$slug}");
        $post = $this->posts->getById($draft['id']);

        $this->assertSame("/blog/{$slug}", $draft['address']);
        $this->assertSame('noindex,nofollow', $page['robots']);
        $this->assertSame((int) $this->pageRow('blog')['id'], (int) $page['parent_page_id']);
        $this->assertSame(0, (int) $post['is_published']);
        $this->assertNull($post['published_at']);
        $this->assertNull($post['author_user_id']);
    }

    public function test_creating_a_draft_is_logged_once_with_the_new_id_and_title(): void
    {
        $draft = $this->newDraft('logged');

        $this->assertSame([['Created', $draft['id'], "{$this->runTag} logged"]], $this->log);
    }

    public function test_the_address_comes_from_the_title_unless_a_slug_is_given(): void
    {
        $fromTitle = $this->draft()->create("Hello, World! {$this->runTag}", 'Text');
        $fromSlug = $this->draft()->create('Anything', 'Text', "My Custom {$this->runTag}");

        $this->assertSame('/blog/hello-world-' . \strtolower($this->runTag), $fromTitle['address']);
        $this->assertSame('/blog/my-custom-' . \strtolower($this->runTag), $fromSlug['address']);
    }

    public function test_the_body_is_stored_as_escaped_paragraphs_so_markup_cannot_get_in(): void
    {
        $draft = $this->newDraft('markup', "One\n\n<script>alert(1)</script>");

        $body = $this->posts->getById($draft['id'])['body'];

        $this->assertStringContainsString('<p>One</p>', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function test_a_blank_excerpt_is_stored_as_nothing_and_the_description_goes_on_the_page(): void
    {
        $draft = $this->draft()->create("{$this->runTag} extras", 'Text', '', '   ', ' A summary for search ');

        $this->assertNull($this->posts->getById($draft['id'])['excerpt']);
        $this->assertSame('A summary for search', $this->pageRow('blog/' . \strtolower($this->runTag) . '-extras')['description']);
    }

    public function test_an_address_that_is_already_used_is_refused_and_leaves_no_new_page_behind(): void
    {
        $this->newDraft('taken');
        $pagesBefore = $this->pageCount();

        try {
            $this->newDraft('taken');
            $this->fail('A second draft with the same address was accepted');
        } catch (\InvalidArgumentException) {
            $this->assertSame($pagesBefore, $this->pageCount());
            $this->assertCount(1, $this->log);
        }
    }

    public function test_if_saving_the_post_fails_the_page_just_made_is_removed_so_no_orphan_is_left(): void
    {
        $failing = new class ($this->db) extends BlogPost {
            public function create(int $pageId, ?string $excerpt, string $body, ?string $heroImage, ?string $socialImage, ?int $authorUserId, bool $isPublished, ?string $publishedAt): int
            {
                throw new \RuntimeException('The post could not be saved');
            }
        };
        $pagesBefore = $this->pageCount();

        try {
            (new BlogDraft($failing, $this->adminPages, fn() => null))->create("{$this->runTag} orphan", 'Text');
            $this->fail('The failure was swallowed');
        } catch (\RuntimeException $e) {
            $this->assertSame('The post could not be saved', $e->getMessage());
        }

        $this->assertSame($pagesBefore, $this->pageCount());
        $this->assertSame([], $this->log);
    }

    public function test_a_title_with_nothing_usable_for_an_address_is_refused(): void
    {
        $pagesBefore = $this->pageCount();

        try {
            $this->draft()->create('!!! ???', 'Text');
            $this->fail('An empty address was accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Slug is required', $e->getMessage());
            $this->assertSame($pagesBefore, $this->pageCount());
        }
    }

    // ---- the bin ------------------------------------------------------------------------------------------

    public function test_delete_moves_the_page_to_the_bin_but_keeps_the_post(): void
    {
        $draft = $this->newDraft('binned');
        $this->log = [];

        $post = $this->bin()->delete($draft['id']);

        $this->assertSame($draft['id'], (int) $post['id']);
        $this->assertNotNull($this->pageRow('blog/' . \strtolower($this->runTag) . '-binned')['deleted_at']);
        $this->assertNull($this->posts->getById($draft['id']));
        $this->assertNotNull($this->posts->getDeletedById($draft['id']));
        $this->assertSame([['Deleted', $draft['id'], "{$this->runTag} binned"]], $this->log);
    }

    public function test_a_deleted_post_moves_between_the_live_list_and_the_bin_list(): void
    {
        $draft = $this->newDraft('lists');
        $ids = fn(array $rows): array => \array_map(static fn(array $row): int => (int) $row['id'], $rows);

        $this->assertContains($draft['id'], $ids($this->posts->listAll()));
        $this->assertNotContains($draft['id'], $ids($this->posts->listDeleted()));

        $this->bin()->delete($draft['id']);

        $this->assertNotContains($draft['id'], $ids($this->posts->listAll()));
        $this->assertContains($draft['id'], $ids($this->posts->listDeleted()));
    }

    public function test_restore_brings_the_page_and_post_back(): void
    {
        $draft = $this->newDraft('back');
        $this->bin()->delete($draft['id']);
        $this->log = [];

        $this->bin()->restore($draft['id']);

        $this->assertNull($this->pageRow('blog/' . \strtolower($this->runTag) . '-back')['deleted_at']);
        $this->assertNotNull($this->posts->getById($draft['id']));
        $this->assertSame([['Restored', $draft['id'], "{$this->runTag} back"]], $this->log);
    }

    public function test_deleting_and_restoring_never_publish_anything(): void
    {
        $draft = $this->newDraft('stays-draft');
        $this->bin()->delete($draft['id']);
        $this->bin()->restore($draft['id']);

        $post = $this->posts->getById($draft['id']);

        $this->assertSame(0, (int) $post['is_published']);
        $this->assertNull($post['published_at']);
        $this->assertSame('noindex,nofollow', $this->pageRow('blog/' . \strtolower($this->runTag) . '-stays-draft')['robots']);
    }

    public function test_an_unknown_post_cannot_be_deleted_or_restored_and_a_live_one_cannot_be_restored(): void
    {
        $draft = $this->newDraft('live');

        foreach ([fn() => $this->bin()->delete(987654321), fn() => $this->bin()->restore(987654321), fn() => $this->bin()->restore($draft['id'])] as $attempt) {
            try {
                $attempt();
                $this->fail('A bad bin request was accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertNotNull($this->posts->getById($draft['id']));
    }

    public function test_a_redirect_on_delete_must_stay_on_this_site_and_nothing_is_deleted_when_it_does_not(): void
    {
        $draft = $this->newDraft('redirect');

        foreach (['https://evil.example/x', '//evil.example/x', 'javascript:alert(1)'] as $target) {
            try {
                $this->bin()->delete($draft['id'], $target);
                $this->fail("Accepted {$target}");
            } catch (\InvalidArgumentException) {
                $this->assertNotNull($this->posts->getById($draft['id']), $target);
            }
        }
    }

    // ---- the tool wrappers: refusals happen before anything is changed -------------------------------------

    public function test_the_tool_refuses_a_delete_with_the_wrong_title_and_changes_nothing(): void
    {
        $draft = $this->newDraft('guarded');

        try {
            BlogMcp::delete($this->call(['id' => $draft['id'], 'expected_title' => 'Some other title']));
            $this->fail('A wrong title was accepted');
        } catch (McpToolException $e) {
            $this->assertStringContainsString('does not match', $e->getMessage());
        }
        $this->assertNotNull($this->posts->getById($draft['id']));
    }

    public function test_the_tool_refuses_an_unknown_id_for_delete_and_restore(): void
    {
        foreach (['delete', 'restore'] as $method) {
            try {
                BlogMcp::$method($this->call(['id' => 987654321, 'expected_title' => 'Anything']));
                $this->fail("{$method} accepted an unknown post");
            } catch (McpToolException $e) {
                $this->assertStringContainsString('No matching post', $e->getMessage());
            }
        }
    }

    public function test_the_tool_will_not_restore_a_post_that_is_not_in_the_bin(): void
    {
        $draft = $this->newDraft('not-binned');

        try {
            BlogMcp::restore($this->call(['id' => $draft['id'], 'expected_title' => "{$this->runTag} not-binned"]));
            $this->fail('A live post was restored');
        } catch (McpToolException $e) {
            $this->assertStringContainsString('No matching post', $e->getMessage());
        }
    }

    public function test_the_list_tool_hides_the_bin_unless_asked_and_never_shows_an_author(): void
    {
        $live = $this->newDraft('listed-live');
        $gone = $this->newDraft('listed-gone');
        $this->bin()->delete($gone['id']);

        $normal = BlogMcp::postsList($this->call([]));
        $bin = BlogMcp::postsList($this->call(['deleted' => true]));
        $titles = fn(array $result): array => \array_column($result['posts'], 'title');

        $this->assertContains("{$this->runTag} listed-live", $titles($normal));
        $this->assertNotContains("{$this->runTag} listed-gone", $titles($normal));
        $this->assertContains("{$this->runTag} listed-gone", $titles($bin));
        $this->assertNotContains("{$this->runTag} listed-live", $titles($bin));
        $this->assertStringNotContainsString('author', \json_encode($normal));
        $this->assertSame($live['id'], (int) \array_values(\array_filter($normal['posts'], fn(array $p): bool => $p['title'] === "{$this->runTag} listed-live"))[0]['id']);
    }
}
