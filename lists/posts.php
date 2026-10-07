<?php

declare(strict_types=1);

/**
 * Blog posts list, rendered by Osmium\Modules\Admin\Core\ListView. Rows are shaped by BlogController::formatPost().
 */
return [
    'posts' => [
        'id' => 'blog-posts',
        'title' => 'Posts',
        'noun' => ['post', 'posts'],
        'endpoint' => 'blog/action/',
        'datatable' => false,
        'emptyText' => 'No posts yet. Use New Post to write the first.',
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
            ['key' => 'date_label', 'label' => 'Date', 'type' => 'text', 'nowrap' => true],
        ],
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'url' => '{edit_url}'],
            ['label' => 'View on site', 'icon' => 'bx-link-external', 'url' => '{url_path}', 'target' => '_blank'],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'action' => 'delete',
                'confirm' => 'Delete this post? Its page is removed too.',
                'danger' => true,
            ],
        ],
    ],
];
