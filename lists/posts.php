<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * Blog posts list, rendered by Osmium\Modules\Admin\Core\ListView. Rows are shaped by BlogController::formatPost().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

return [
    'recent' => [
        'id' => 'blog-posts-recent',
        'title' => 'Recent Changes',
        'noun' => ['post', 'posts'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'blog/action/',
        'datatable' => false,
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))],
            ['key' => 'title', 'label' => 'Post', 'type' => 'text', 'strong' => true, 'fallback' => 'Untitled', 'fill' => true],
            [
                'key' => 'status_label',
                'label' => 'Status',
                'type' => 'badge',
                'text' => true,
                'classKey' => 'status_class',
                'classPrefix' => 'bg-label-',
            ],
            ['key' => 'updated_label', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

    'posts' => [
        'id' => 'blog-posts',
        'title' => 'Posts',
        'noun' => ['post', 'posts'],
        'endpoint' => 'blog/action/',
        'emptyText' => 'No posts yet. Use New Post to write the first.',
        'order' => null, // keep the server's newest-first order until a header is clicked
        'columns' => [
            [
                'key' => 'title',
                'label' => 'Post',
                'type' => 'text',
                'strong' => true,
                'fallback' => 'Untitled',
                'fill' => true,
                'sub' => ['key' => 'url_path', 'style' => 'link', 'href' => '{url_path}'],
            ],
            ['key' => 'author_name', 'label' => 'Author', 'type' => 'text', 'nowrap' => true, 'fallback' => '-'],
            [
                'key' => 'status_label',
                'label' => 'Status',
                'type' => 'badge',
                'text' => true,
                'classKey' => 'status_class',
                'classPrefix' => 'bg-label-',
            ],
            ['key' => 'date_label', 'label' => 'Date', 'type' => 'text', 'nowrap' => true, 'sortKey' => 'date_sort'],
        ],
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'url' => '{edit_url}'],
            ['label' => 'View on site', 'icon' => 'bx-link-external', 'url' => '{url_path}', 'target' => '_blank'],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'event' => 'delete',
                'danger' => true,
            ],
        ],
    ],

    'deleted' => ListView::deletedPreset(
        definition: [
            'id' => 'blog-posts-deleted',
            'title' => 'Deleted Posts',
            'noun' => ['post', 'posts'],
            'endpoint' => 'blog/action/',
            'hideWhenEmpty' => false,
            'columns' => [
                ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))],
                [
                    'key' => 'title',
                    'label' => 'Post',
                    'type' => 'text',
                    'strong' => true,
                    'fallback' => 'Untitled',
                    'fill' => true,
                    'sub' => ['key' => 'url_path', 'style' => 'plain'],
                ],
                ['key' => 'author_name', 'label' => 'Author', 'type' => 'text', 'nowrap' => true, 'fallback' => '-'],
            ],
        ],
        permanentConfirm: 'Permanently delete this post and its page? This cannot be undone!',
    ),
];
