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
    private const THUMB_SIDE = 320;
    private const THUMB_QUALITY = 75;

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
     * The small copy of a stored image that the admin lists show, named beside it (photo-ab12cd.webp -> photo-ab12cd-thumb.webp)
     */
    public static function thumbPath(string $path): string
    {
        return \substr($path, 0, -5) . '-thumb.webp';
    }

    /**
     * Make the thumbnail when it is missing or older than the image it came from. A new upload has a new file name,
     * so a changed hero always gets a fresh thumbnail.
     */
    public function ensureThumbnail(string $path, string $documentRoot): void
    {
        if (!self::isBlogImage($path)) return;

        $root = \rtrim($documentRoot, '/');
        $source = $root . $path;
        $thumb = $root . self::thumbPath($path);
        if (!\is_file($source)) return;
        if (\is_file($thumb) && \filemtime($thumb) >= \filemtime($source)) return;

        $tools = new ImageTools();
        $small = $tools->fit($tools->load($source, ['image/webp']), self::THUMB_SIDE);
        if (!\imagewebp(image: $small, file: $thumb, quality: self::THUMB_QUALITY)) throw new \RuntimeException('Could not save the thumbnail');
    }

    /**
     * The path the lists should show: the thumbnail when it exists, otherwise the full image
     */
    public static function listImage(string $path, string $documentRoot): string
    {
        if (!self::isBlogImage($path)) return $path;

        $thumb = self::thumbPath($path);

        return \is_file(\rtrim($documentRoot, '/') . $thumb) ? $thumb : $path;
    }

    private function safeName(string $originalName): string
    {
        $base = \pathinfo($originalName, \PATHINFO_FILENAME);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($base)), '-');

        return $slug === '' ? 'image' : \substr($slug, 0, 60);
    }
}
