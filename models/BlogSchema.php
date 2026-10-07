<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * Builds a post's BlogPosting JSON-LD, so a theme prints one tag and never assembles schema by hand.
 */
class BlogSchema
{
    /**
     * @param array<string, mixed> $post a row from BlogPost::findPublishedByPageId()
     * @param array<string, mixed> $data the page data core hands a claim (site, page, template)
     */
    public static function script(array $post, array $data): string
    {
        $site = $data['site'] ?? [];
        $fqdn = (string) ($site['FQDN'] ?? '');
        $siteName = (string) ($site['name'] ?? '');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => (string) ($site['fullURL'] ?? '')],
            'headline' => (string) ($data['page']['title'] ?? ''),
            'description' => (string) ($data['template']['metaDescription'] ?? ''),
            'image' => (string) ($site['socialImage'] ?? ''),
            'datePublished' => self::iso($post['published_at'] ?? null),
            'dateModified' => self::iso($post['updated_at'] ?? $post['published_at'] ?? null),
            'author' => self::author($post, $siteName),
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
        ];

        $logo = $site['logoDark'] ?? $site['logoLight'] ?? null;
        if ($logo) $schema['publisher']['logo'] = ['@type' => 'ImageObject', 'url' => $fqdn . $logo];

        $schema = \array_filter($schema, fn ($value) => $value !== '' && $value !== null);

        // HEX_TAG keeps a post title containing "</script>" from ending the tag early
        $json = \json_encode($schema, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP);

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    public static function authorName(array $post): string
    {
        return \trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? ''));
    }

    private static function author(array $post, string $siteName): array
    {
        $name = self::authorName($post);
        if ($name !== '') return ['@type' => 'Person', 'name' => $name];

        return ['@type' => 'Organization', 'name' => $siteName];
    }

    private static function iso(?string $date): string
    {
        $time = $date ? \strtotime($date) : false;

        return $time === false ? '' : \date('c', $time);
    }
}
