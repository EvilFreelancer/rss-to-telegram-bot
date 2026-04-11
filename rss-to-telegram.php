<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Compolomus\RssReader\RssReader;
use Symfony\Component\Dotenv\Dotenv;

// Load env variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = new Dotenv();
    $dotenv->load(__DIR__ . '/.env');
}

// Read settings
$tgChannelName = $_ENV['TELEGRAM_CHANNEL_NAME'];
$tgToken       = $_ENV['TELEGRAM_TOKEN'];

// Parse list of sources
$sources = explode(',', $_ENV['RSSREADER_SOURCES']);

// If no sources then error
if (empty($sources)) {
    throw new \RuntimeException('There is no sources');
}

// Convert categories to hash-tags
$showCategoriesAsTags = (bool) ($_ENV['RSSREADER_SHOW_CATEGORIES_AS_TAGS'] ?? false);

function prepareHTML(string $string): string
{
    // Replace <br/> tag to a EOL
    $string = preg_replace('/\<br(\s*)?\/?\>/i', "\n\n", $string);
    // Replace couple </p><p> to a EOL
    $string = preg_replace('/\<\/p\>\s*\<p\>/i', "\n\n", $string);
    // Cleanup other tags, keep only <a>
    $string = strip_tags($string, ['a']);

    // Process <a>...</a> blocks: clean attributes, drop relative URLs, normalize entities
    $string = preg_replace_callback('/<a\s[^>]*>([^<]*)<\/a>/i', function ($matches) {
        $innerText = $matches[1];

        if (!preg_match('/href\s*=\s*["\']([^"\']*)["\']/', $matches[0], $hrefMatch)) {
            return $innerText;
        }

        $href = $hrefMatch[1];
        if (!preg_match('#^https?://#i', $href)) {
            return $innerText;
        }

        // Normalize & encoding in URL: first decode all &amp; then re-encode
        $href = str_replace('&amp;', '&', $href);
        $href = str_replace('&', '&amp;', $href);

        // Decode HTML entities in link text, then re-escape for Telegram
        $innerText = html_entity_decode($innerText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $innerText = htmlspecialchars($innerText, ENT_NOQUOTES, 'UTF-8', false);

        return '<a href="' . $href . '">' . $innerText . '</a>';
    }, $string);

    // Process text segments outside <a> tags: decode entities then escape for Telegram HTML
    // ENT_NOQUOTES: only escape <, >, & (Telegram does not support &apos; entity)
    $string = preg_replace_callback('/(?<=>|^)([^<]+)(?=<|$)/s', function ($matches) {
        $text = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false);
    }, $string);

    // Remove multiple new lines
    $string = preg_replace('/\n{3,}/', "\n\n", $string);

    return trim($string);
}

function renderTemplate(array $data): string
{
    global $showCategoriesAsTags, $tgChannelName;

    // Escape title for Telegram HTML (only <, >, & need escaping)
    $safeTitle = htmlspecialchars($data['title'], ENT_NOQUOTES, 'UTF-8', false);

    // Escape & in link URL for valid HTML attribute
    $safeLink = str_replace('&', '&amp;', $data['link']);

    // Default template
    $template = '<a href="' . $safeLink . '">' . $safeTitle . "</a>\n\n{{description}}\n\n";

    // Fix template of categories as tags mode enabled
    if ($showCategoriesAsTags) {
        $rawCategories = $data['categories'];
        if (!is_array($rawCategories)) {
            $rawCategories = [$rawCategories];
        }
        $categories = [];
        foreach ($rawCategories as $category) {
            // Fix spaces and special characters
            $categories[] = '#' . preg_replace('/[\s\-\+\&\(\)\.\/]+/', '_', mb_strtolower($category));
        }
        $categoriesStr = implode(' ', $categories);

        if (!empty($categories)) {
            $template .= $categoriesStr . " | ";
        }
    }

    // Append name of telegram channel
    $template .= "@" . $tgChannelName;

    $result = str_replace("{{description}}", $data['description'], $template);
    return $result;
}

// Read channels
$rss = new RssReader($sources);

// Get all posts
$posts = $rss->getAll();

function sendTelegram(string $token, string $method, array $params): array
{
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || !is_string($response)) {
        return [
            'ok'          => false,
            'description' => 'curl error: ' . ($curlError !== '' ? $curlError : 'empty response'),
            '_curl_errno' => $curlErrno,
            '_http_code'  => $httpCode,
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'ok'          => false,
            'description' => 'invalid JSON from Telegram API',
            '_http_code'  => $httpCode,
            '_raw_prefix' => substr($response, 0, 200),
        ];
    }

    $decoded['_http_code'] = $httpCode;
    return $decoded;
}

// Submit all received posts to Telegram
foreach ($posts as $post) {
    // Build text message
    $message = renderTemplate([
        'link'        => $post['link'] ?? '',
        'title'       => $post['title'] ?? '',
        'categories'  => $post['category'] ?? [],
        'description' => prepareHTML($post['description'] ?? ''),
    ]);

    $params = [
        'chat_id'                  => '@' . $tgChannelName,
        'disable_notification'     => true,
        'disable_web_page_preview' => false,
        'parse_mode'               => 'HTML',
        'text'                     => $message,
    ];

    echo '>>> ' . $post['_id'] . ' ' . ($post['title'] ?? '') . PHP_EOL;

    $response = sendTelegram($tgToken, 'sendMessage', $params);

    if (!empty($response['ok'])) {
        echo '    OK' . PHP_EOL;
        // Persist immediately so a later crash or timeout does not re-post the same items
        $rss->cache->saveIds([$post['_id']]);
    } else {
        $desc = $response['description'] ?? 'unknown error';
        echo '    FAILED: ' . $desc . PHP_EOL;
    }

    sleep(5);
}
