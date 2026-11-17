<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../rss-template.php';

final class RenderTemplateTest extends TestCase
{
    public function testRendersArrayFieldsFromRssItems(): void
    {
        $message = renderTemplate([
            'title' => ['A & B', 'Second title', '@attributes' => ['type' => 'html']],
            'link' => 'https://example.test/post?a=1&b=2',
            'description' => ['<p>First &amp; item</p>', '<p>Second item</p>'],
            'categories' => ['Tech News', 'AI/ML'],
        ], true, 'news_channel');

        self::assertStringContainsString('<a href="https://example.test/post?a=1&amp;b=2">A &amp; B', $message);
        self::assertStringContainsString('Second title', $message);
        self::assertStringContainsString('First &amp; item', $message);
        self::assertStringContainsString('Second item', $message);
        self::assertStringContainsString('#tech_news #ai_ml | @news_channel', $message);
    }

    public function testRendersScalarFieldsWithoutCategoryTags(): void
    {
        $message = renderTemplate([
            'title' => 'Title',
            'link' => 'https://example.test/',
            'description' => '<p>Body</p>',
            'categories' => 'Tech',
        ], false, 'news_channel');

        self::assertSame('<a href="https://example.test/">Title</a>' . "\n\n" . 'Body' . "\n\n@news_channel", $message);
    }
}
