<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Compolomus\RssReader\RssReader;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/rss-template.php';

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
        'description' => $post['description'] ?? '',
    ], $showCategoriesAsTags, $tgChannelName);

    $params = [
        'chat_id'                  => '@' . $tgChannelName,
        'disable_notification'     => true,
        'disable_web_page_preview' => false,
        'parse_mode'               => 'HTML',
        'text'                     => $message,
    ];

    echo '>>> ' . $post['_id'] . ' ' . normalizeFeedText($post['title'] ?? '') . PHP_EOL;

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
