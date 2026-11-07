<?php

declare(strict_types=1);

/**
 * Grid (card) layout for the Posts list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/posts.php but nothing is shared with it. The actions are a copy of the list's for now.
 */
return [
    'columns' => 4,
    'imageHeight' => 130,
    'image' => ['key' => 'hero_image', 'type' => 'image', 'altKey' => 'title'],
    'title' => [
        'key' => 'title',
        'type' => 'text',
        'strong' => true,
        'fallback' => 'Untitled',
        'sub' => ['key' => 'author_name', 'style' => 'muted'],
    ],
    'meta' => [
        [
            'key' => 'status_label',
            'type' => 'badge',
            'text' => true,
            'classKey' => 'status_class',
            'classPrefix' => 'bg-label-',
        ],
        ['key' => 'date_label', 'type' => 'text', 'small' => true],
    ],
    'actionsAt' => 'bottom-end',
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
];
