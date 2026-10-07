<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

class BlogPost extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'blog_posts');
    }

    /**
     * The published post behind a pages row, or null when the page is not a post (or is still a draft)
     */
    public function findPublishedByPageId(int $pageId): ?array
    {
        $sql = $this->loadSqlFile('blog-post-get-published-by-page.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':page_id', value: $pageId);
        $post = $this->database->single();

        return $post === false ? null : $post;
    }

    /**
     * The post behind a pages row whatever its state (draft, scheduled or published), for an admin's preview
     */
    public function findByPageId(int $pageId): ?array
    {
        $this->database->query($this->loadSqlFile('blog-post-get-by-page.sql'));
        $this->database->bind(param: ':page_id', value: $pageId);
        $post = $this->database->single();

        return $post === false ? null : $post;
    }

    /**
     * Every post with its page's url and title and its author's name, newest first
     */
    public function listAll(): array
    {
        $this->database->query($this->loadSqlFile('blog-post-list.sql'));

        return $this->database->resultset();
    }

    public function getById(int $id): ?array
    {
        $this->database->query($this->loadSqlFile('blog-post-get.sql'));
        $this->database->bind(param: ':id', value: $id);
        $post = $this->database->single();

        return $post === false ? null : $post;
    }

    /**
     * Active admin users who can be named as an author
     */
    public function authors(): array
    {
        $this->database->query($this->loadSqlFile('blog-authors.sql'));

        return $this->database->resultset();
    }

    public function create(int $pageId, ?string $excerpt, string $body, ?string $heroImage, ?string $socialImage, ?int $authorUserId, bool $isPublished, ?string $publishedAt): int
    {
        $this->database->query($this->loadSqlFile('blog-post-insert.sql'));
        $this->database->bind(param: ':page_id', value: $pageId);
        $this->bindContent($excerpt, $body, $heroImage, $socialImage, $authorUserId, $isPublished, $publishedAt);
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, ?string $excerpt, string $body, ?string $heroImage, ?string $socialImage, ?int $authorUserId, bool $isPublished, ?string $publishedAt): void
    {
        $this->database->query($this->loadSqlFile('blog-post-update.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->bindContent($excerpt, $body, $heroImage, $socialImage, $authorUserId, $isPublished, $publishedAt);
        $this->database->execute();
    }

    public function delete(int $id): void
    {
        $this->database->query($this->loadSqlFile('blog-post-delete.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    private function bindContent(?string $excerpt, string $body, ?string $heroImage, ?string $socialImage, ?int $authorUserId, bool $isPublished, ?string $publishedAt): void
    {
        $this->database->bind(param: ':excerpt', value: $excerpt);
        $this->database->bind(param: ':body', value: $body);
        $this->database->bind(param: ':hero_image', value: $heroImage);
        $this->database->bind(param: ':social_image', value: $socialImage);
        $this->database->bind(param: ':author_user_id', value: $authorUserId);
        $this->database->bind(param: ':is_published', value: $isPublished ? 1 : 0);
        $this->database->bind(param: ':published_at', value: $publishedAt);
    }

    /**
     * Pages the editor can link to: ordinary pages, shop categories and products, and published
     * posts (never drafts). Each row is id, url, title and type (page|category|product|post).
     */
    public function linkTargets(): array
    {
        $this->database->query($this->loadSqlFile('blog-link-targets.sql'));

        return $this->database->resultset();
    }

    /**
     * Published posts, newest first, for the blog index and a theme's "latest posts". Each row has
     * id, excerpt, hero_image, published_at, url (the page address without a leading slash - link
     * with $urls->to('/' . $url . '/')), title and the author's first_name and last_name. Posts dated
     * in the future stay hidden until their time.
     */
    public function published(int $limit, int $offset = 0): array
    {
        $this->database->query($this->loadSqlFile('blog-post-list-published.sql'));
        $this->database->bind(param: ':limit', value: $limit, type: \PDO::PARAM_INT);
        $this->database->bind(param: ':offset', value: $offset, type: \PDO::PARAM_INT);

        return $this->database->resultset();
    }

    public function latest(int $count): array
    {
        return $this->published(limit: $count);
    }

    public function countPublished(): int
    {
        $this->database->query($this->loadSqlFile('blog-post-count-published.sql'));

        return (int) ($this->database->single()['total'] ?? 0);
    }

    /**
     * Every pages row a post sits behind, as post id and page id, for the admin Pages list
     */
    public function ownedPages(): array
    {
        $this->database->query($this->loadSqlFile('blog-owned-pages.sql'));

        return $this->database->resultset();
    }

    public function indexPageId(string $url): ?int
    {
        $this->database->query($this->loadSqlFile('blog-index-page.sql'));
        $this->database->bind(param: ':url', value: $url);
        $row = $this->database->single();

        return $row === false ? null : (int) $row['id'];
    }
}
