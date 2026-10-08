<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Modules\Admin\Services\AdminPages;
use Osmium\Services\Blog\Models\BlogClaim;
use Osmium\Services\Blog\Models\BlogImages;
use Osmium\Services\Blog\Models\BlogPost;

/**
 * Blog admin - posts list, the editor, and the AJAX actions behind them.
 *
 * Every post is also an ordinary pages row at blog/<slug> (title, meta description and url live there),
 * so the page, its sitemap entry and its redirects work like any other page. See BlogClaim for how
 * the public request finds the post again.
 *
 * Routes:
 *   - index()  → /admin/blog/
 *   - edit()   → /admin/blog/edit/?id=N   (no id: a new post)
 *   - action() → /admin/blog/action/      (save, delete, link_targets, upload_image)
 */
class BlogController extends AdminController
{
    private const RECORD_TYPE = 'blog_post';
    private const URL_PREFIX = 'blog/';

    public function index(): void
    {
        require_once 'app/modules/admin/core/ListView.php';

        $posts = (new BlogPost($this->osmium->dataSource))->listAll();

        $this->data['admin']['isAdmin'] = $this->isAdmin();
        $this->data['admin']['posts'] = \array_map($this->formatPost(...), $posts);
        $this->data['admin']['deletedPosts'] = \array_map($this->formatPost(...), (new BlogPost($this->osmium->dataSource))->listDeleted());
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/posts.php';

        $this->setView('blog/index.phtml');
    }

    public function edit(): void
    {
        $model = new BlogPost($this->osmium->dataSource);

        $id = (int) $this->osmium->getStashedParam(key: 'id', default: 0); // Core moves query params out of $_GET
        $post = $id ? $model->getById($id) : null;
        if ($id && !$post) $this->redirect('blog/');

        $slug = $post ? \substr($post['url'], \strlen(self::URL_PREFIX)) : '';
        $this->data['admin']['post'] = [
            'id' => $id,
            'title' => $post['title'] ?? '',
            'slug' => $slug,
            'description' => $post['description'] ?? '',
            'excerpt' => $post['excerpt'] ?? '',
            'heroImage' => $post['hero_image'] ?? '',
            'socialImage' => $post['social_image'] ?? '',
            'body' => $post['body'] ?? '',
            'authorUserId' => (int) ($post['author_user_id'] ?? $this->getCurrentUserId()),
            'isPublished' => (bool) ($post['is_published'] ?? false),
            'publishedAt' => isset($post['published_at']) ? \date('Y-m-d\TH:i', \strtotime($post['published_at'])) : '',
        ];
        $this->data['admin']['authors'] = $model->authors();

        $this->setView('blog/edit.phtml');
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $this->isPost();
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $action = $input['action'] ?? '';
        $id = (int) ($input['id'] ?? 0);

        try {
            $result = match ($action) {
                'save' => $this->save(id: $id, input: $input),
                'delete' => $this->deletePost($id),
                'restore' => $this->restorePost($id),
                'permanently_delete' => $this->permanentlyDeletePost($id),
                'upload_image' => $this->uploadImage($_FILES['image'] ?? []),
                'link_targets' => ['success' => true, 'targets' => $this->linkTargets()],
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->admin->jsonSuccess($result);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Action helpers
    // =========================================================================

    private function save(int $id, array $input): array
    {
        $title = \trim((string) ($input['title'] ?? ''));
        if ($title === '') throw new \InvalidArgumentException('Title is required');

        $slug = $this->cleanSlug((string) ($input['slug'] ?? '') ?: $title);
        if ($slug === '') throw new \InvalidArgumentException('Slug is required');

        $body = (string) ($input['body'] ?? '');
        $heroImage = $this->ownImage((string) ($input['hero_image'] ?? ''));
        $socialImage = $this->ownImage((string) ($input['social_image'] ?? ''));
        $isPublished = !empty($input['is_published']);
        $excerpt = \trim((string) ($input['excerpt'] ?? '')) ?: null;
        $authorUserId = (int) ($input['author_user_id'] ?? 0) ?: null;
        $publishedAt = $this->publishedAt(input: $input, isPublished: $isPublished);

        $pages = $this->pages();
        $model = new BlogPost($this->osmium->dataSource);

        $pageFields = [
            'url' => self::URL_PREFIX . $slug,
            'title' => $title,
            'description' => \trim((string) ($input['description'] ?? '')),
            'robots' => $isPublished ? 'index,follow' : 'noindex,nofollow', // A draft stays out of the sitemap
            'parent_page_id' => $this->blogIndexPageId(),
        ];

        if ($id === 0) {
            $pageId = $pages->create($pageFields);
            $id = $model->create($pageId, $excerpt, $body, $heroImage, $socialImage, $authorUserId, $isPublished, $publishedAt);
            $this->logChange(action: 'Created', id: $id, name: $title);

            return ['success' => true, 'id' => $id];
        }

        $existing = $model->getById($id);
        if (!$existing) throw new \InvalidArgumentException('Post not found');

        $pages->update((int) $existing['page_id'], $pageFields); // Redirects the old url when the slug changed
        $model->update($id, $excerpt, $body, $heroImage, $socialImage, $authorUserId, $isPublished, $publishedAt);
        $this->logChange(action: 'Updated', id: $id, name: $title);

        return ['success' => true, 'id' => $id];
    }

    /**
     * @return array<int, array{type: string, title: string, url: string}>
     */
    private function linkTargets(): array
    {
        $trailingSlash = $this->osmium->config->site->urlTrailingSlash ?? false;
        $targets = [];
        foreach ((new BlogPost($this->osmium->dataSource))->linkTargets() as $page) {
            $isHome = \in_array($page['url'], ['', '/'], true);
            $url = $isHome ? '/' : '/' . $page['url'] . ($trailingSlash ? '/' : '');
            $targets[] = [
                'type' => $page['type'],
                'title' => (string) ($page['title'] ?: $page['url']),
                'url' => $url,
            ];
        }

        return $targets;
    }

    /**
     * @return array{success: bool, url: string, width: int, height: int}
     */
    private function uploadImage(array $file): array
    {
        $maxUpload = \Osmium\Modules\Admin\Services\OsmiumAdmin::getMaxUploadSize();
        $stored = (new BlogImages())->store(file: $file, maxBytes: $maxUpload['bytes'], documentRoot: $_SERVER['DOCUMENT_ROOT']);

        return ['success' => true] + $stored;
    }

    /**
     * A hero or social image path is kept only when it is one this service stored
     */
    private function ownImage(string $path): ?string
    {
        return BlogImages::isBlogImage($path) ? $path : null;
    }

    /**
     * Soft delete: the page goes to the Pages bin and the post row stays, so a restore brings it all back
     */
    private function deletePost(int $id): array
    {
        $post = (new BlogPost($this->osmium->dataSource))->getById($id);
        if (!$post) throw new \InvalidArgumentException('Post not found');

        $this->pages()->delete(id: (int) $post['page_id'], input: []);
        $this->logChange(action: 'Deleted', id: $id, name: (string) $post['title']);

        return ['success' => true];
    }

    private function restorePost(int $id): array
    {
        $post = (new BlogPost($this->osmium->dataSource))->getDeletedById($id);
        if (!$post) throw new \InvalidArgumentException('Deleted post not found');

        $this->pages()->restore((int) $post['page_id']);
        $this->logChange(action: 'Restored', id: $id, name: (string) $post['title']);

        return ['success' => true];
    }

    /**
     * Admins only, and only from the bin. The page goes for good too: a restored page would be an empty 404,
     * and a soft-deleted row would keep the address from being reused
     */
    private function permanentlyDeletePost(int $id): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \RuntimeException('Permission denied');

        $model = new BlogPost($this->osmium->dataSource);
        $post = $model->getDeletedById($id);
        if (!$post) throw new \InvalidArgumentException('Deleted post not found');

        $model->delete($id);
        $this->pages()->permanentDelete((int) $post['page_id']);
        $this->logChange(action: 'Permanently deleted', id: $id, name: (string) $post['title']);

        return ['success' => true];
    }

    /**
     * Publishing stamps the date once; a date typed into the editor wins, but never a future one: a published
     * post is live now (no scheduling), so a draft is the only unpublished state and the only noindex one
     */
    private function publishedAt(array $input, bool $isPublished): ?string
    {
        $typed = \trim((string) ($input['published_at'] ?? ''));
        $typedTime = $typed === '' ? false : \strtotime($typed);
        if ($typedTime !== false) {
            $time = $isPublished ? \min($typedTime, \time()) : $typedTime;

            return \date('Y-m-d H:i:s', $time);
        }

        return $isPublished ? \date('Y-m-d H:i:s') : null;
    }

    private function cleanSlug(string $text): string
    {
        $slug = \strtolower(\trim($text));
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return \trim($slug, '-');
    }

    private function pages(): AdminPages
    {
        return new AdminPages(
            dataSource: $this->osmium->dataSource,
            sqlDir: 'app/modules/admin/models/sql/',
            urlTrailingSlash: $this->osmium->config->site->urlTrailingSlash ?? false,
        );
    }

    /**
     * The blog index page (/blog), created the first time a post is saved so posts have breadcrumbs
     * to lead back to and the index has an address of its own
     */
    private function blogIndexPageId(): ?int
    {
        foreach (\Osmium\Core\Models\Model::pagesById() as $page) {
            $isBlogIndex = ($page['url'] ?? '') === BlogClaim::INDEX_SLUG && ($page['deleted_at'] ?? null) === null;
            if ($isBlogIndex) return (int) $page['id'];
        }

        return $this->pages()->create([
            'url' => BlogClaim::INDEX_SLUG,
            'title' => 'Blog',
            'description' => '',
            'robots' => 'index,follow',
        ]);
    }

    private function formatPost(array $post): array
    {
        $isPublished = (bool) $post['is_published'];
        $trailingSlash = $this->osmium->config->site->urlTrailingSlash ?? false;
        $author = \trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? ''));
        $date = $post['published_at'] ?? $post['updated_at'];

        return $post + [
            'url_path' => '/' . $post['url'] . ($trailingSlash ? '/' : ''),
            'edit_url' => $this->getBasePath() . 'blog/edit/?id=' . (int) $post['id'],
            'author_name' => $author,
            'status_label' => $isPublished ? 'Published' : 'Draft',
            'status_class' => $isPublished ? 'success' : 'secondary',
            'date_label' => $date ? \date('j M Y', \strtotime($date)) : '',
        ];
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    private function logChange(string $action, int $id, string $name): void
    {
        $this->admin->model->changelog->log(
            description: $action . ': ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }
}
