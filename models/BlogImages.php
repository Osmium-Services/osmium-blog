<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * Stores images uploaded in the blog editor: checks the real file type, applies the camera's rotation,
 * scales anything wider than MAX_WIDTH down, and saves one WebP under assets/images/blog/.
 * GIF is refused because GD would flatten an animation.
 */
class BlogImages
{
    public const URL_PREFIX = '/assets/images/blog/';
    private const MAX_WIDTH = 1600;
    private const WEBP_QUALITY = 82;

    private const DECODERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/avif' => 'imagecreatefromavif',
    ];

    /**
     * @param array{tmp_name?: string, name?: string, error?: int, size?: int} $file one entry of $_FILES
     * @return array{url: string, width: int, height: int}
     */
    public function store(array $file, int $maxBytes, string $documentRoot): array
    {
        $uploadFailed = !isset($file['error']) || $file['error'] !== \UPLOAD_ERR_OK;
        if ($uploadFailed) throw new \InvalidArgumentException('No file uploaded');

        $tooLarge = ($file['size'] ?? 0) > $maxBytes;
        if ($tooLarge) throw new \InvalidArgumentException('File too large');

        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $decoder = self::DECODERS[$mimeType] ?? null;
        if ($decoder === null) throw new \InvalidArgumentException('Use a JPG, PNG, WebP or AVIF image');
        if (!\function_exists($decoder) || !\function_exists('imagewebp')) {
            throw new \InvalidArgumentException("This server can't process {$mimeType} images. Try JPG or PNG instead.");
        }

        $source = $decoder($file['tmp_name']);
        if (!$source) throw new \InvalidArgumentException('That image could not be read');

        if ($mimeType === 'image/jpeg') $source = $this->applyOrientation($source, $file['tmp_name']);

        $image = $this->scaleDown($source);

        $directory = \rtrim($documentRoot, '/') . self::URL_PREFIX;
        if (!\is_dir($directory) && !\mkdir($directory, 0755, true) && !\is_dir($directory)) {
            throw new \RuntimeException('Could not create the blog image folder');
        }

        $filename = $this->safeName((string) ($file['name'] ?? 'image')) . '-' . \bin2hex(\random_bytes(3)) . '.webp';
        $saved = \imagewebp($image, $directory . $filename, self::WEBP_QUALITY);
        if (!$saved) throw new \RuntimeException('Could not save the image');

        return [
            'url' => self::URL_PREFIX . $filename,
            'width' => \imagesx($image),
            'height' => \imagesy($image),
        ];
    }

    /**
     * Whether a stored path is one of ours, so a post can't point its hero at an arbitrary address
     */
    public static function isBlogImage(string $path): bool
    {
        return \preg_match('#^' . \preg_quote(self::URL_PREFIX, '#') . '[a-z0-9._-]+\.webp$#', $path) === 1;
    }

    private function scaleDown(\GdImage $source): \GdImage
    {
        $width = \imagesx($source);
        $height = \imagesy($source);
        if ($width <= self::MAX_WIDTH) return $this->keepTransparency($source);

        $scaled = \imagescale($source, self::MAX_WIDTH, -1, \IMG_BICUBIC);

        return $scaled ? $this->keepTransparency($scaled) : $source;
    }

    private function keepTransparency(\GdImage $image): \GdImage
    {
        \imagepalettetotruecolor($image);
        \imagealphablending($image, false);
        \imagesavealpha($image, true);

        return $image;
    }

    private function applyOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!\function_exists('exif_read_data')) return $image;

        $exif = @\exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) return $image;

        $rotated = \imagerotate($image, $angle, 0);

        return $rotated ?: $image;
    }

    private function safeName(string $originalName): string
    {
        $base = \pathinfo($originalName, \PATHINFO_FILENAME);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($base)), '-');

        return $slug === '' ? 'image' : \substr($slug, 0, 60);
    }
}
