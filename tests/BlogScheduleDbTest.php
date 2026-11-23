<?php

declare(strict_types=1);

namespace Tests\Services\OsmiumBlog;

use Osmium\Services\Blog\Models\BlogPost;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../models/BlogPost.php';

/**
 * Scheduled posts (published, dated in the future) against the installed schema, rolled back with each test.
 * The blog service must be installed on the test database.
 */
class BlogScheduleDbTest extends DatabaseTestCase
{
    private function post(int $pageId, bool $published, string $when): int
    {
        return (new BlogPost($this->db))->create($pageId, null, '<p>x</p>', null, null, null, $published, $when);
    }

    public function test_a_future_dated_published_post_is_scheduled_and_not_live_until_its_time(): void
    {
        $posts = new BlogPost($this->db);
        $this->post(900001, true, \date('Y-m-d H:i:s', \time() - 3600));
        $this->post(900002, true, \date('Y-m-d H:i:s', \time() + 3600));
        $this->post(900003, false, \date('Y-m-d H:i:s', \time() + 3600));

        $this->assertNotNull($posts->findPublishedByPageId(900001));
        $this->assertNull($posts->findPublishedByPageId(900002), 'a scheduled post is not live');
        $this->assertNull($posts->findPublishedByPageId(900003), 'a draft is not live');
        $this->assertNotNull($posts->findByPageId(900002), 'an admin preview still finds it');

        $this->assertSame([900002], \array_values(\array_intersect($posts->scheduledPageIds(), [900001, 900002, 900003])));
    }
}
