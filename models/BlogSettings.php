<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * This service's settings, read from app/config/services/osmium-blog.json.php (the file-based convention the
 * other services use). Missing file means the defaults: a new post is credited to whoever is writing it, and
 * the posts live under /blog. `indexSlug` moves them under another section (for example `guides`). It is editable in
 * the admin settings screen, which warns when posts exist: their stored addresses do not move with it.
 */
class BlogSettings
{
    public const CONFIG_PATH = 'app/config/services/osmium-blog.json.php';
    public const DEFAULT_INDEX_SLUG = 'blog';

    private static ?array $settings = null;

    public static function get(): array
    {
        if (self::$settings !== null) return self::$settings;

        $settings = self::defaults();

        if (\file_exists(self::CONFIG_PATH)) {
            $content = (string) \file_get_contents(self::CONFIG_PATH);
            $jsonStart = \strpos(haystack: $content, needle: '{');
            $stored = $jsonStart === false ? null : \json_decode(\substr(string: $content, offset: $jsonStart), associative: true);

            if (\is_array($stored['blog'] ?? null)) {
                $settings = \array_merge($settings, \array_intersect_key($stored['blog'], $settings));
            }
        }

        $settings['defaultAuthorId'] = \max(0, (int) $settings['defaultAuthorId']);
        $settings['indexSlug'] = BlogDraft::cleanSlug((string) $settings['indexSlug']) ?: self::DEFAULT_INDEX_SLUG;

        return self::$settings = $settings;
    }

    /**
     * @param int $defaultAuthorId User credited on a new post; 0 means the signed-in user
     * @param string|null $indexSlug The section the blog lives under; null keeps the current one
     */
    public static function save(int $defaultAuthorId, ?string $indexSlug = null): void
    {
        $dir = \dirname(self::CONFIG_PATH);
        if (!\is_dir($dir)) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $data = ['blog' => ['defaultAuthorId' => \max(0, $defaultAuthorId), 'indexSlug' => $indexSlug ?? self::indexSlug()]];

        $json = \json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        \file_put_contents(self::CONFIG_PATH, "<?php exit(); ?>\n" . $json . "\n");

        self::$settings = null;
    }

    /** The section the blog lives under: the address of the index page and the prefix of every post */
    public static function indexSlug(): string
    {
        return self::get()['indexSlug'];
    }

    private static function defaults(): array
    {
        return ['defaultAuthorId' => 0, 'indexSlug' => self::DEFAULT_INDEX_SLUG];
    }
}
