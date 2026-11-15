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
    private const MAX_WIDTH = 1600;
    private const WEBP_QUALITY = 82;
    private const THUMB_SIDE = 240;

    /**
     * @param array{tmp_name?: string, name?: string, error?: int, size?: int} $file one entry of $_FILES
     * @return array{url: string, width: int, height: int}
     */
    public function store(array $file, int $maxBytes, string $documentRoot): array
    {
        $directory = \rtrim($documentRoot, '/') . self::URL_PREFIX;
        $baseName = $this->safeName((string) ($file['name'] ?? 'image'));
        $filename = $baseName . '-' . \bin2hex(\random_bytes(3)) . '.webp';

        $size = (new ImageTools())->storeAsWebp(
            file: $file,
            maxBytes: $maxBytes,
            path: $directory . $filename,
            maxWidth: self::MAX_WIDTH,
            quality: self::WEBP_QUALITY,
        );

        return ['url' => self::URL_PREFIX . $filename, 'width' => $size['width'], 'height' => $size['height']];
    }

    /**
     * Whether a stored path is one of ours, so a post can't point its hero at an arbitrary address
     */
    public static function isBlogImage(string $path): bool
    {
        return \preg_match('#^' . \preg_quote(self::URL_PREFIX, '#') . '[a-z0-9._-]+\.webp$#', $path) === 1;
    }

    /**
     * The small copies of a hero image that the admin lists show, in a thumb/ folder beside it with avif/ and webp/
     * folders under that, the same layout as the shop and carousel so core's Image can pick the best one. No extension:
     * /assets/images/blog/photo.jpg -> /assets/images/blog/thumb/photo
     */
    public static function thumbBase(string $path): string
    {
        return \dirname($path) . '/thumb/' . \str_replace('.', '-', \pathinfo($path, \PATHINFO_FILENAME)); // A dot in the name would read as an extension to core's Image
    }

    /**
     * Make the thumbnails (JPG, plus WebP and AVIF where this PHP can write them) when missing or older than the image
     * they came from. Works for any image on this site, so imported posts get them too. A new hero is a different file.
     */
    public function ensureThumbnail(string $path, string $documentRoot): void
    {
        $source = self::localFile($path, $documentRoot);
        if ($source === null) return;

        $base = \rtrim($documentRoot, '/') . self::thumbBase($path);
        $jpg = $base . '.jpg';
        if (\is_file($jpg) && \filemtime($jpg) >= \filemtime($source)) return;

        $tools = new ImageTools();
        $small = $tools->fit($tools->load($source, ['image/webp', 'image/jpeg', 'image/png']), self::THUMB_SIDE);
        $tools->storeVariants($small, \dirname($base), \basename($base), ['jpg' => 75, 'webp' => 70, 'avif' => 50]);
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
