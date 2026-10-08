<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * This service's settings, read from app/config/services/blog.json.php (the file-based convention the
 * other services use). Missing file means the defaults: a new post is credited to whoever is writing it.
 */
class BlogSettings
{
    public const CONFIG_PATH = 'app/config/services/blog.json.php';

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

        return self::$settings = $settings;
    }

    /**
     * @param int $defaultAuthorId User credited on a new post; 0 means the signed-in user
     */
    public static function save(int $defaultAuthorId): void
    {
        $dir = \dirname(self::CONFIG_PATH);
        if (!\is_dir($dir)) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $data = ['blog' => ['defaultAuthorId' => \max(0, $defaultAuthorId)]];

        $json = \json_encode(value: $data, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        \file_put_contents(self::CONFIG_PATH, "<?php exit(); ?>\n" . $json . "\n");

        self::$settings = null;
    }

    private static function defaults(): array
    {
        return ['defaultAuthorId' => 0];
    }
}
