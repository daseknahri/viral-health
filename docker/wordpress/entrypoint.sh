#!/bin/sh
set -e

export WP_CLI_ALLOW_ROOT=1
export WP_CLI_PHP_ARGS="${WP_CLI_PHP_ARGS:--d memory_limit=512M}"

mkdir -p /var/www/html/wp-content/themes /var/www/html/wp-content/mu-plugins /var/www/html/wp-content/plugins
mkdir -p /var/www/html/wp-content/uploads

rm -rf /var/www/html/wp-content/themes/kepoli
rm -rf /var/www/html/wp-content/plugins/kepoli-author-tools
rm -rf /var/www/html/wp-content/plugins/dr-purg-social-syndicator
cp -a /opt/kepoli/wp-content/themes/kepoli /var/www/html/wp-content/themes/kepoli
cp -a /opt/kepoli/wp-content/mu-plugins/. /var/www/html/wp-content/mu-plugins/
cp -a /opt/kepoli/wp-content/plugins/kepoli-author-tools /var/www/html/wp-content/plugins/kepoli-author-tools
cp -a /opt/kepoli/wp-content/plugins/dr-purg-social-syndicator /var/www/html/wp-content/plugins/dr-purg-social-syndicator

cat > /var/www/html/.htaccess <<'HTACCESS'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS

chown -R www-data:www-data \
  /var/www/html/wp-content/themes/kepoli \
  /var/www/html/wp-content/mu-plugins \
  /var/www/html/wp-content/plugins/kepoli-author-tools \
  /var/www/html/wp-content/plugins/dr-purg-social-syndicator \
  /var/www/html/wp-content/uploads \
  /seed \
  /content 2>/dev/null || true
chown www-data:www-data /var/www/html/.htaccess 2>/dev/null || true
chmod 664 /var/www/html/.htaccess 2>/dev/null || true

if [ "${1:-}" = "apache2-foreground" ] && [ -x /seed/bin/bootstrap.sh ]; then
  (
    /seed/bin/bootstrap.sh
    chown -R www-data:www-data /var/www/html/wp-content /var/www/html/wp-config.php 2>/dev/null || true
  ) &
fi

exec docker-entrypoint.sh "$@"
