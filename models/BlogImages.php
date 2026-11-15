<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

use Osmium\Core\Library\ImageTools;

/**
 * Stores images uploaded in the blog editor as one WebP under assets/images/blog/. The checking, rotation and
 * scaling are core's ImageTools; this class picks the folder and the file name.
 */
class BlogImages
{
    public const URL_PREFIX = '/assets/images/blog/';
    private const MAX_WIDTH = 1920;
    private const WEBP_QUALITY = 82;
    /** Name suffix => longest side in pixels: the admin list shows a 50px box, the grid view about 250px wide */
    public const THUMBS = ['list' => 136, 'grid' => 240];

    /**
     * @param array{tmp_name?: string, name?: string, error?: int, size?: int} $file one entry of $_FILES
     * @param string $postSlug The post's address name: its images go in a folder of that name, as on the old site
     * @return array{url: string, width: int, height: int}
     */
    public function store(array $file, int $maxBytes, string $documentRoot, string $postSlug = ''): array
    {
        $folder = \trim((string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($postSlug)), '-');
        $folder = $folder === '' ? '' : \substr($folder, 0, 100) . '/';
        $directory = \rtrim($documentRoot, '/') . self::URL_PREFIX . $folder;
        $baseName = $this->safeName((string) ($file['name'] ?? 'image'));
        $filename = $baseName . '-' . \bin2hex(\random_bytes(3)) . '.webp';

        $size = (new ImageTools())->storeAsWebp(
            file: $file,
            maxBytes: $maxBytes,
            path: $directory . $filename,
            maxWidth: self::MAX_WIDTH,
            quality: self::WEBP_QUALITY,
        );

        return ['url' => self::URL_PREFIX . $folder . $filename, 'width' => $size['width'], 'height' => $size['height']];
    }

    /**
     * Whether a stored path is one of ours, so a post can't point its hero at an arbitrary address
     */
    public static function isBlogImage(string $path): bool
    {
        return \preg_match('#^' . \preg_quote(self::URL_PREFIX, '#') . '([a-z0-9-]+/)?[a-z0-9._-]+\.webp$#', $path) === 1;
    }

    /**
     * The small copies of a hero image that the admin lists show, named beside it with -list or -grid on the name, and
     * their webp/ and avif/ copies in the same folders as the hero's own, so core's Image can pick the best one.
     * No extension: /assets/images/blog/post/photo.jpg -> /assets/images/blog/post/photo-list
     */
    public static function thumbBase(string $path, string $kind): string
    {
        $name = \str_replace('.', '-', \pathinfo($path, \PATHINFO_FILENAME)); // A dot in the name would read as an extension to core's Image

        return \dirname($path) . '/' . $name . '-' . $kind;
    }

    /**
     * Make the thumbnails (JPG, plus WebP and AVIF where this PHP can write them) in each size when missing or older than
     * the image they came from. Works for any image on this site, so imported posts get them too. A new hero is a different file.
     */
    public function ensureThumbnail(string $path, string $documentRoot): void
    {
        $source = self::localFile($path, $documentRoot);
        if ($source === null) return;

        $tools = new ImageTools();
        $image = null;
        foreach (self::THUMBS as $kind => $side) {
            $base = \rtrim($documentRoot, '/') . self::thumbBase($path, $kind);
            $jpg = $base . '.jpg';
            if (\is_file($jpg) && \filemtime($jpg) >= \filemtime($source)) continue;

            $image ??= $tools->load($source, ['image/webp', 'image/jpeg', 'image/png']);
            $tools->storeVariants($tools->fit($image, $side), \dirname($base), \basename($base), ['jpg' => 75, 'webp' => 70, 'avif' => 50]);
        }
    }

    /**
     * Make the hero's own WebP and AVIF copies (webp/name.webp and avif/name.avif beside it) when missing or older than
     * the hero, so core's Image can give the browser the best one. Copies that are already there are left alone.
     */
    public function ensureOptimised(string $path, string $documentRoot): void
    {
        $source = self::localFile($path, $documentRoot);
        if ($source === null) return;

        $tools = new ImageTools();
        $image = $tools->load($source, ['image/webp', 'image/jpeg', 'image/png']);
        $tools->storeOptimised($image, \dirname($source), \pathinfo($source, \PATHINFO_FILENAME), ['webp' => self::WEBP_QUALITY, 'avif' => 60], (int) \filemtime($source));
    }

    /**
     * Full file path of a site-relative image, or null for an address elsewhere, a missing file, or a path that climbs out of the site
     */
    private static function localFile(string $path, string $documentRoot): ?string
    {
        if ($path === '' || $path[0] !== '/' || \str_contains($path, '..') || \str_contains($path, '?')) return null;

        $file = \rtrim($documentRoot, '/') . $path;

        return \is_file($file) ? $file : null;
    }

    private function safeName(string $originalName): string
    {
        $base = \pathinfo($originalName, \PATHINFO_FILENAME);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($base)), '-');

        return $slug === '' ? 'image' : \substr($slug, 0, 60);
    }
}
