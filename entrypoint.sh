#!/bin/bash

# If timeout is not set then use 3600 seconds (1 hour) by default
TS_TIMEOUT="${TS_TIMEOUT:-3600}"

# If directory is not set, then use defaults
RSSREADER_CACHE_DIR="${RSSREADER_CACHE_DIR:-/app/cache}"

# Change ownership to user=nobody, group=nobody
chown -R nobody:nobody "$RSSREADER_CACHE_DIR"

# Run infinity loop
while true; do
  su -s /bin/bash -c "php -f /app/rss-to-telegram.php" nobody
  sleep $TS_TIMEOUT
done
