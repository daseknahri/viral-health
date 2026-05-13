#!/bin/sh
set -e

export WP_CLI_ALLOW_ROOT=1
export WP_CLI_PHP_ARGS="${WP_CLI_PHP_ARGS:--d memory_limit=512M}"

mkdir -p /var/www/html/wp-content/themes /var/www/html/wp-content/mu-plugins /var/www/html/wp-content/plugins

rm -rf /var/www/html/wp-content/themes/kepoli
rm -rf /var/www/html/wp-content/plugins/kepoli-author-tools
rm -rf /var/www/html/wp-content/plugins/dr-purg-social-syndicator
cp -a /opt/kepoli/wp-content/themes/kepoli /var/www/html/wp-content/themes/kepoli
cp -a /opt/kepoli/wp-content/mu-plugins/. /var/www/html/wp-content/mu-plugins/
cp -a /opt/kepoli/wp-content/plugins/kepoli-author-tools /var/www/html/wp-content/plugins/kepoli-author-tools
cp -a /opt/kepoli/wp-content/plugins/dr-purg-social-syndicator /var/www/html/wp-content/plugins/dr-purg-social-syndicator

chown -R 33:33 \
  /var/www/html/wp-content/themes/kepoli \
  /var/www/html/wp-content/mu-plugins \
  /var/www/html/wp-content/plugins/kepoli-author-tools \
  /var/www/html/wp-content/plugins/dr-purg-social-syndicator \
  /seed \
  /content 2>/dev/null || true

/bin/sh /seed/bin/bootstrap.sh

chown -R 33:33 \
  /var/www/html/wp-content \
  /var/www/html/wp-config.php 2>/dev/null || true
