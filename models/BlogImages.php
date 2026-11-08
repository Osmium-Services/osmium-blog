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

    private function safeName(string $originalName): string
    {
        $base = \pathinfo($originalName, \PATHINFO_FILENAME);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($base)), '-');

        return $slug === '' ? 'image' : \substr($slug, 0, 60);
    }
}
