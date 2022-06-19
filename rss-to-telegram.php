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

// Read channels
$rss = new RssReader([
    'https://3dnews.ru/breaking/rss/',
]);

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

    dd($url);
    exec('curl ' . $url);
}
