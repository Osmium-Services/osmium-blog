<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * Server-side clean-up of a post's HTML body, with the same allow-list as the editor's DOMPurify config
 * (public/blog-edit.js). The browser check is only a courtesy: this runs on save whatever sent the request.
 * Pure.
 */
class BlogHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'blockquote',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'img', 'sup', 'sub',
    ];
    private const ALLOWED_ATTRIBUTES = [
        'href', 'target', 'rel', 'src', 'alt', 'width', 'height', 'loading', 'colspan', 'rowspan', 'scope',
    ];
    private const URL_ATTRIBUTES = ['href', 'src'];
    private const DROPPED_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math'];

    /** Tags and attributes outside the allow-list are removed; links and images keep only web, mail, phone or relative addresses. */
    public static function sanitize(string $html): string
    {
        $isBlank = \trim($html) === '';
        if ($isBlank) return '';

        $document = new \DOMDocument();
        $previous = \libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="blog-root">' . $html . '</div>', LIBXML_NOENT | LIBXML_NONET);
        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);

        $root = $document->getElementById('blog-root');
        if (!$root) return '';

        self::cleanChildren($root);

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return \trim($clean);
    }

    private static function cleanChildren(\DOMNode $parent): void
    {
        foreach (\iterator_to_array($parent->childNodes) as $child) {
            $isElement = $child instanceof \DOMElement;
            if (!$isElement) {
                $isText = $child instanceof \DOMText;
                if (!$isText) $parent->removeChild($child); // Comments, processing instructions
                continue;
            }

            self::cleanElement($parent, $child);
        }
    }

    private static function cleanElement(\DOMNode $parent, \DOMElement $element): void
    {
        $tag = \strtolower($element->tagName);

        $dropWhole = \in_array($tag, self::DROPPED_WITH_CONTENT, true);
        if ($dropWhole) {
            $parent->removeChild($element);
            return;
        }

        self::cleanChildren($element);

        $isAllowed = \in_array($tag, self::ALLOWED_TAGS, true);
        if (!$isAllowed) {
            self::unwrap($parent, $element); // Keep the text, lose the tag
            return;
        }

        self::cleanAttributes($element);
    }

    private static function unwrap(\DOMNode $parent, \DOMElement $element): void
    {
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function cleanAttributes(\DOMElement $element): void
    {
        foreach (\iterator_to_array($element->attributes) as $attribute) {
            $name = \strtolower($attribute->name);
            $isAllowed = \in_array($name, self::ALLOWED_ATTRIBUTES, true);
            $isUrl = \in_array($name, self::URL_ATTRIBUTES, true);
            $isUnsafeUrl = $isUrl && !self::isSafeUrl($attribute->value);

            if (!$isAllowed || $isUnsafeUrl) $element->removeAttribute($attribute->name);
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = \preg_replace('/[\x00-\x20\x7f]+/', '', $url) ?? ''; // Browsers ignore control characters and spaces inside a scheme
        $hasScheme = \preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $match) === 1;
        if (!$hasScheme) return true; // Relative address, fragment or query

        return \in_array(\strtolower($match[1]), ['http', 'https', 'mailto', 'tel'], true);
    }
}
