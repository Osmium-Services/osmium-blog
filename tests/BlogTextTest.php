<?php

declare(strict_types=1);

namespace Tests\Services\Blog;

use Osmium\Services\Blog\Models\BlogDraft;
use Osmium\Services\Blog\Models\BlogText;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/BlogText.php';

/**
 * Plain text to the HTML a post stores, and the address a title turns into. Everything typed is escaped, so
 * a draft written over MCP can never carry markup.
 */
final class BlogTextTest extends TestCase
{
    public function test_a_blank_line_starts_a_new_paragraph_and_a_single_break_stays_a_break(): void
    {
        $html = BlogText::paragraphs("First line\nstill first\n\nSecond paragraph");

        $this->assertSame("<p>First line<br>\nstill first</p>\n<p>Second paragraph</p>", $html);
    }

    public function test_windows_and_old_mac_line_endings_work_the_same(): void
    {
        $this->assertSame(BlogText::paragraphs("A\n\nB"), BlogText::paragraphs("A\r\n\r\nB"));
        $this->assertSame(BlogText::paragraphs("A\n\nB"), BlogText::paragraphs("A\r\rB"));
    }

    public function test_html_typed_into_the_text_is_shown_as_text_and_cannot_run(): void
    {
        $html = BlogText::paragraphs('<script>alert(1)</script> & "quotes" and \'single\'');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('&quot;quotes&quot;', $html);
    }

    public function test_empty_or_blank_text_gives_no_paragraphs(): void
    {
        $this->assertSame('', BlogText::paragraphs(''));
        $this->assertSame('', BlogText::paragraphs("  \n\n  \n"));
    }

    public function test_extra_blank_lines_do_not_create_empty_paragraphs(): void
    {
        $this->assertSame("<p>A</p>\n<p>B</p>", BlogText::paragraphs("A\n\n\n\n\nB"));
    }

    public function test_a_title_becomes_a_lowercase_hyphenated_address(): void
    {
        $this->assertSame('hello-world-2026', BlogDraft::cleanSlug('Hello, World! 2026'));
        $this->assertSame('my-slug', BlogDraft::cleanSlug('  My Slug  '));
        $this->assertSame('a-b', BlogDraft::cleanSlug('a / b'));
    }

    public function test_characters_that_cannot_be_in_an_address_never_survive(): void
    {
        $this->assertSame('', BlogDraft::cleanSlug('!!! ???'));
        $this->assertSame('x', BlogDraft::cleanSlug('../x/..'));
        $this->assertSame('a-script-b', BlogDraft::cleanSlug('a<script>b'));
    }

    public function test_script_json_cannot_close_a_script_tag_or_break_out_of_a_quote(): void
    {
        $json = BlogText::scriptJson(['title' => '</script><img src=x onerror=alert(1)> & "q" \'s']);

        foreach (['<', '>', '&', "'"] as $char) {
            $this->assertStringNotContainsString($char, $json, $char);
        }
        $this->assertSame('</script><img src=x onerror=alert(1)> & "q" \'s', \json_decode($json, true)['title'], 'it still decodes to the original');
    }
}
