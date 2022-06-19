<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Compolomus\RssReader\RssReader;
use Symfony\Component\Dotenv\Dotenv;

// Load env variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Read settings
$tgChannelName = $_ENV['TELEGRAM_CHANNEL_NAME'];
$tgToken       = $_ENV['TELEGRAM_TOKEN'];

// Parse list of sources
$sources = explode(',', $_ENV['RSSREADER_SOURCES']);

// If no sources then error
if (empty($sources)) {
    throw new \RuntimeException('There is no sources');
}

// Read channels
$rss = new RssReader($sources);

// Get all posts
$posts = $rss->getAll();

// Submit all received posts to Telegram
foreach ($posts as $post) {
    // Build text message
    $message  = '[' . $post['title'] . '](' . $post['link'] . ')' . PHP_EOL . html_entity_decode($post['desc']);
    $endpoint = 'sendMessage';
    $query    = [
        'chat_id'                  => '@' . $tgChannelName,
        'disable_notification'     => true,
        'disable_web_page_preview' => false,
        'parse_mode'               => 'Markdown',
        'text'                     => $message,
    ];

    $url = 'https://api.telegram.org/bot' . $tgToken . '/' . $endpoint . '?' . http_build_query($query);

    exec('curl ' . $url);
}
