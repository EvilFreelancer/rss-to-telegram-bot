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
    // Convert entities like &nbsp; and &arr; to symbols
    $string = html_entity_decode($string);
    // Replace <br/> tag to a EOL
    $string = preg_replace('/\<br(\s*)?\/?\>/i', "\n\n", $string);
    // Replace couple </p><p> to a EOL
    $string = preg_replace('/\<\/p\>\s*\<p\>/i', "\n\n", $string);
    // Cleanup other tags
    $string = strip_tags($string, ['a']);
    // Remove multiple new lines
    $string = preg_replace('/\n{3,}/', "\n\n", $string);

    return $string;
}

function renderTemplate(array $data): string
{
    global $showCategoriesAsTags, $tgChannelName;

    // Default template
    $template = "<a href='{{link}}'>{{title}}</a>\n\n{{description}}\n\n";

    // Fix template of categories as tags mode enabled
    if ($showCategoriesAsTags) {
        // Parse categories
        $categories = [];
        foreach ($data['categories'] as $category) {
            // Fix spaces and special characters
            $categories[] = '#' . preg_replace('/[\s\-\+\&\(\)\.]+/', '_', mb_strtolower($category));
        }
        $data['categories'] = implode(' ', $categories);

        // Fix template too
        if (!empty($categories)) {
            $template .= "{{categories}} | ";
        }
    } else {
        unset($data['categories']);
    }

    // Append name of telegram channel
    $template .= "@" . $tgChannelName;

    $result = $template;
    foreach ($data as $key => $value) {
        $result = str_replace("{{" . $key . "}}", $value, $result);
    }
    return $result;
}

// Read channels
$rss = new RssReader($sources);

// Get all posts
$posts = $rss->getAll();

// Submit all received posts to Telegram
foreach ($posts as $post) {
    // Build text message
    $message  = renderTemplate([
        'link'        => $post['link'] ?? '',
        'title'       => $post['title'] ?? '',
        'categories'  => $post['category'] ?? [],
        'description' => prepareHTML($post['description'] ?? ''),
    ]);
    $endpoint = 'sendMessage';
    $query    = [
        'chat_id'                  => '@' . $tgChannelName,
        'disable_notification'     => true,
        'disable_web_page_preview' => false,
        'parse_mode'               => 'HTML',
        'text'                     => $message,
    ];

    $url = 'https://api.telegram.org/bot' . $tgToken . '/' . $endpoint . '?' . http_build_query($query);

    echo '>>> ' . $post['_id'] . PHP_EOL;
    echo exec('curl "' . $url . '"') . PHP_EOL;
    sleep(5);
}
