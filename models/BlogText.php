<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Models;

/**
 * Plain-text helpers for blog content. Pure.
 */
class BlogText
{
    /**
     * Plain text to the HTML the editor stores: a blank line starts a new paragraph and a single line break
     * stays a <br>. Everything is escaped, so a tag typed into the text shows as text and cannot run.
     */
    public static function paragraphs(string $text): string
    {
        $text = \str_replace(["\r\n", "\r"], "\n", \trim($text));
        if ($text === '') return '';

        $html = [];
        foreach (\preg_split('/\n{2,}/', $text) ?: [] as $paragraph) {
            $paragraph = \trim($paragraph);
            if ($paragraph === '') continue;

            $html[] = '<p>' . \nl2br(\htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'), false) . '</p>';
        }

        return \implode("\n", $html);
    }
}
