<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

use Osmium\Modules\Admin\Services\AdminPages;

/**
 * Soft delete and restore for blog posts, shared by the admin screen and the MCP tools so both behave the same.
 * The page goes to the Pages bin and the post row stays, so a restore brings it all back. Permanent delete is
 * deliberately not here: it is admin-only and lives in the controller.
 */
class BlogBin
{
    /** @param \Closure(string, int, string): void $log called with the action ('Deleted', 'Restored'), post id and post title */
    public function __construct(
        private BlogPost $posts,
        private AdminPages $pages,
        private \Closure $log,
    ) {}

    /**
     * An optional redirect sends the old address to another page of the site until the post is restored.
     *
     * @return array<string, mixed> the post that was deleted
     */
    public function delete(int $id, string $redirectTarget = ''): array
    {
        $post = $this->posts->getById($id);
        if (!$post) throw new \InvalidArgumentException('Post not found');

        $redirectTarget = \trim($redirectTarget);
        $isSitePath = $redirectTarget === '' || (\str_starts_with($redirectTarget, '/') && !\str_starts_with($redirectTarget, '//'));
        if (!$isSitePath) throw new \InvalidArgumentException('Redirect to a page of this site, starting with /');

        $this->pages->delete(id: (int) $post['page_id'], input: ['redirect_target' => $redirectTarget]);
        ($this->log)('Deleted', $id, (string) $post['title']);

        return $post;
    }

    /** @return array<string, mixed> the post that was restored */
    public function restore(int $id): array
    {
        $post = $this->posts->getDeletedById($id);
        if (!$post) throw new \InvalidArgumentException('Deleted post not found');

        $this->pages->restore((int) $post['page_id']);
        ($this->log)('Restored', $id, (string) $post['title']);

        return $post;
    }
}
