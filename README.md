# RSS to Telegram bot

Small project for scraping RSS feed and repost new messages to Telegram group/channel.

## How to use

1. Clone repo

```shell
git clone https://github.com/EvilFreelancer/rss-to-telegram-bot.git
```

2. Switch to project root

```shell
cd rss-to-telegram-bot
```

3. Create `.env` file from example

```shell
cp .env.dist .env
```

4. Fix `.env` parameters, you at least need to add list of RSS feed sources and API token of Telegram-bot

5. Run the script

```shell
php -f rss-to-telegram.php
```

## Example docker-compose.yml

This project already has Dockerfile, so you may build your own
docker image and use it.

```yaml
version: "2.4"

services:

  rss-to-telegram-bot:
    restart: "unless-stopped"
    build:
      context: .
    environment:
      # Timeout in seconds between requests to rss feed
      RSSREADER_TIMEOUT: 600
      # Path to RSS feed cache folder
      RSSREADER_CACHE_DIR: /app/cache
      # Limiting the number of recent posts
      RSSREADER_LIMIT: 50
      # List of comma-separated URLs of RSS sources
      RSSREADER_SOURCES: https://portal1/news/rss/,https://portal2/rss/
      # If you want to show categories as hash-tags
      RSSREADER_SHOW_CATEGORIES_AS_TAGS: false
      # Telegram Channel Name
      TELEGRAM_CHANNEL_NAME: evilfreelancer
      # Telegram Access Token
      TELEGRAM_TOKEN: nnnnnnnnnnnnn:xxxXXXxXxXxXxXXxXxxXxxxxXx
    volumes:
      - ./bot_cache:/app/cache:rw
    logging:
      driver: "json-file"
      options:
        max-size: "50k"
```

## Environment variables

| Name                              | Default value                         | Description                                 |
|-----------------------------------|---------------------------------------|---------------------------------------------|
| RSSREADER_TIMEOUT                 | 3600 _(in seconds)_                   | Timeout between requests for updates        |
| RSSREADER_CACHE_DIR               | /tmp/.rssreader_cache                 | Path to directory with cache                |
| RSSREADER_LIMIT                   | 0 _(0, empty or not set - unlimited)_ | Limiting the number of recent posts         |
| RSSREADER_SOURCES                 | _null_                                | List of comma-separated URLs of RSS sources | 
| RSSREADER_SHOW_CATEGORIES_AS_TAGS | false                                 | If you want to show categories as hash-tags | 
| TELEGRAM_CHANNEL_NAME             | _null_                                | Telegram Channel Name                       |
| TELEGRAM_TOKEN                    | _null_                                | Telegram Access Token                       |

## Links

* https://github.com/Compolomus/RssReader
