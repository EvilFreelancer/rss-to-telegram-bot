<?php declare(strict_types=1);

function normalizeFeedText(mixed $value): string
{
    if (!is_array($value)) {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    $parts = [];
    foreach ($value as $key => $item) {
        if (is_string($key) && str_starts_with($key, '@')) {
            continue;
        }
        $text = normalizeFeedText($item);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return implode("\n\n", $parts);
}

function prepareHTML(mixed $value): string
{
    $string = normalizeFeedText($value);

    $string = preg_replace('/\<br(\s*)?\/?\>/i', "\n\n", $string);
    $string = preg_replace('/\<\/p\>\s*\<p\>/i', "\n\n", $string);
    $string = strip_tags($string, ['a']);

    $string = preg_replace_callback('/<a\s[^>]*>([^<]*)<\/a>/i', function ($matches) {
        $innerText = $matches[1];

        if (!preg_match('/href\s*=\s*["\']([^"\']*)["\']/', $matches[0], $hrefMatch)) {
            return $innerText;
        }

        $href = $hrefMatch[1];
        if (!preg_match('#^https?://#i', $href)) {
            return $innerText;
        }

        $href = str_replace('&amp;', '&', $href);
        $href = str_replace('&', '&amp;', $href);

        $innerText = html_entity_decode($innerText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $innerText = htmlspecialchars($innerText, ENT_NOQUOTES, 'UTF-8', false);

        return '<a href="' . $href . '">' . $innerText . '</a>';
    }, $string);

    $string = preg_replace_callback('/(?<=>|^)([^<]+)(?=<|$)/s', function ($matches) {
        $text = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false);
    }, $string);

    $string = preg_replace('/\n{3,}/', "\n\n", $string);

    return trim($string);
}

function renderTemplate(array $data, bool $showCategoriesAsTags, string $tgChannelName): string
{
    $safeTitle = htmlspecialchars(normalizeFeedText($data['title'] ?? ''), ENT_NOQUOTES, 'UTF-8', false);
    $safeLink = str_replace('&', '&amp;', normalizeFeedText($data['link'] ?? ''));

    $template = '<a href="' . $safeLink . '">' . $safeTitle . "</a>\n\n";
    $template .= prepareHTML($data['description'] ?? '') . "\n\n";

    if ($showCategoriesAsTags) {
        $rawCategories = $data['categories'] ?? [];
        if (!is_array($rawCategories)) {
            $rawCategories = [$rawCategories];
        }

        $categories = [];
        foreach ($rawCategories as $category) {
            $category = normalizeFeedText($category);
            if ($category === '') {
                continue;
            }
            $categories[] = '#' . preg_replace('/[\s\-\+\&\(\)\.\/]+/', '_', mb_strtolower($category));
        }

        if ($categories !== []) {
            $template .= implode(' ', $categories) . ' | ';
        }
    }

    return $template . '@' . normalizeFeedText($tgChannelName);
}
