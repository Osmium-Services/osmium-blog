<?php

declare(strict_types=1);

namespace Tests\Services\Blog;

use Osmium\Services\Blog\Models\BlogHtml;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/BlogHtml.php';

/**
 * The server-side clean-up of a post body: what the editor allows survives, anything that can run does not.
 */
final class BlogHtmlTest extends TestCase
{
    public function test_ordinary_post_markup_comes_through_unchanged(): void
    {
        $html = '<h2>Title</h2><p>Some <strong>bold</strong>, <em>italic</em> &amp; accented é text.</p><ul><li>One</li></ul>'
            . '<p><a href="/guides/" target="_blank" rel="noopener">Guide</a> <img src="/img/a.png" alt="A" width="10" loading="lazy"></p>'
            . '<table><tbody><tr><td colspan="2">x</td></tr></tbody></table>';

        $this->assertSame($html, BlogHtml::sanitize($html));
    }

    public function test_script_style_and_frame_elements_are_removed_with_their_content(): void
    {
        $clean = BlogHtml::sanitize('<p>a</p><script>alert(1)</script><style>p{}</style><iframe src="https://x.test"></iframe><svg onload="x()"><circle/></svg>');

        $this->assertSame('<p>a</p>', $clean);
    }

    public function test_event_handlers_and_style_attributes_are_removed(): void
    {
        $clean = BlogHtml::sanitize('<p onclick="x()" style="color:red" class="c" id="i">t</p><img src="/a.png" onerror="x()">');

        $this->assertSame('<p>t</p><img src="/a.png">', $clean);
    }

    public function test_script_urls_are_removed_even_when_disguised(): void
    {
        foreach (['javascript:alert(1)', "java\tscript:alert(1)", ' JaVaScRiPt:alert(1)', 'data:text/html,x', 'vbscript:x', '&#106;avascript:alert(1)'] as $bad) {
            $clean = BlogHtml::sanitize('<a href="' . $bad . '">go</a><img src="' . $bad . '">');

            $this->assertStringNotContainsString('script', \strtolower($clean), $bad);
            $this->assertStringNotContainsString('data:', $clean, $bad);
            $this->assertStringNotContainsString('href=', $clean, $bad);
            $this->assertStringNotContainsString('src=', $clean, $bad);
        }
    }

    public function test_web_mail_phone_and_relative_links_are_kept(): void
    {
        foreach (['https://example.test/a?b=1', 'http://example.test', 'mailto:a@example.test', 'tel:+441234', '/page/', '#top', '?x=1'] as $good) {
            $this->assertStringContainsString('href="' . $good . '"', BlogHtml::sanitize('<a href="' . $good . '">go</a>'), $good);
        }
    }

    public function test_other_tags_are_unwrapped_but_their_text_stays(): void
    {
        $this->assertSame('hello <strong>x</strong>', BlogHtml::sanitize('<div><span>hello</span> <font><strong>x</strong></font></div>'));
    }

    public function test_comments_are_removed_and_blank_input_gives_nothing(): void
    {
        $this->assertSame('<p>a</p>', BlogHtml::sanitize('<p>a</p><!-- hidden -->'));
        $this->assertSame('', BlogHtml::sanitize("  \n "));
    }
}
