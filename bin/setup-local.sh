#!/usr/bin/env bash
# Local development stack for this repo, on Ubuntu:
# Apache + PHP-FPM (running as you) + MariaDB + mkcert TLS, serving web/
# at the hostname in .env's WP_HOME (for example https://ronyn.test).
#
# Run as yourself, NOT with sudo; it asks for sudo when it needs it.
# Safe to re-run. See README.md, "Local development".
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
DOCROOT=$ROOT/web
PHPV=${PHPV:-8.5}
ME=$(id -un)
SOCK=/run/php/apex-local.sock

[[ $EUID -ne 0 ]] || { echo "Run as your normal user, not root (it uses sudo itself)."; exit 1; }
[[ -f $ROOT/.env ]] || { echo "Missing $ROOT/.env (copy .env.example first)"; exit 1; }

# Read a value from .env without executing it.
env_get() { sed -nE "s/^$1=['\"]?([^'\"]*)['\"]?[[:space:]]*$/\1/p" "$ROOT/.env" | tail -n1; }
DB_NAME=$(env_get DB_NAME)
DB_USER=$(env_get DB_USER)
DB_PASSWORD=$(env_get DB_PASSWORD)
HOST=$(env_get WP_HOME | sed -E 's#^https?://##; s#/.*##')
[[ -n $DB_NAME && -n $DB_USER && -n $DB_PASSWORD ]] || { echo "DB_NAME, DB_USER and DB_PASSWORD must be set in .env"; exit 1; }
[[ -n $HOST ]] || { echo "WP_HOME must be set in .env"; exit 1; }
if ! getent hosts "$HOST" >/dev/null; then
    echo "Note: $HOST doesn't resolve. Add this line to /etc/hosts:  127.0.0.1 $HOST"
fi

echo "==> Installing packages"
sudo apt-get update -q
sudo apt-get install -y apache2 "php$PHPV-fpm" "php$PHPV-mysql" "php$PHPV-gd" "php$PHPV-imagick" \
    mariadb-server mkcert libnss3-tools

echo "==> PHP-FPM pool running as $ME"
sudo tee "/etc/php/$PHPV/fpm/pool.d/apex-local.conf" >/dev/null <<EOF
[apex-local]
user = $ME
group = $ME
listen = $SOCK
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 8
pm.process_idle_timeout = 30s
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = 120
EOF
sudo systemctl restart "php$PHPV-fpm"

echo "==> TLS certificate for $HOST (mkcert)"
mkcert -install            # trusts mkcert's local CA in the system store and Firefox/Chrome
tmp=$(mktemp -d)
mkcert -cert-file "$tmp/$HOST.pem" -key-file "$tmp/$HOST-key.pem" "$HOST" >/dev/null
sudo install -d -m 755 /etc/apache2/ssl
sudo install -m 644 "$tmp/$HOST.pem" /etc/apache2/ssl/
sudo install -m 640 -g www-data "$tmp/$HOST-key.pem" /etc/apache2/ssl/
rm -rf "$tmp"

echo "==> Letting Apache traverse (not list) your home directory"
case $ROOT in
    "$HOME"/*) sudo setfacl -m u:www-data:x "$HOME" ;;
esac

echo "==> Apache vhost"
sudo tee "/etc/apache2/sites-available/$HOST.conf" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName $HOST
    Redirect permanent / https://$HOST/
</VirtualHost>

<VirtualHost *:443>
    ServerName $HOST
    DocumentRoot $DOCROOT

    SSLEngine on
    SSLCertificateFile    /etc/apache2/ssl/$HOST.pem
    SSLCertificateKeyFile /etc/apache2/ssl/$HOST-key.pem

    <Directory $DOCROOT>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html

        # WordPress pretty permalinks (also covers /error/<code>/)
        RewriteEngine On
        RewriteRule ^index\.php$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.php [L]
    </Directory>

    # Never serve dotfiles, Composer files or the deploy hook locally
    <FilesMatch "^(\.|composer\.(json|lock)$|deploy\.php$)">
        Require all denied
    </FilesMatch>

    <FilesMatch "\.php$">
        SetHandler "proxy:unix:$SOCK|fcgi://localhost"
    </FilesMatch>

    ErrorLog  \${APACHE_LOG_DIR}/$HOST-error.log
    CustomLog \${APACHE_LOG_DIR}/$HOST-access.log combined
</VirtualHost>
EOF
sudo a2enmod -q proxy_fcgi setenvif rewrite ssl headers
sudo a2ensite -q "$HOST"
sudo apache2ctl configtest
sudo systemctl restart apache2

echo "==> MariaDB database '$DB_NAME' and user '$DB_USER'"
pw=${DB_PASSWORD//\\/\\\\}; pw=${pw//\'/\\\'}
sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$pw';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$pw';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> WP-CLI"
if ! command -v wp >/dev/null; then
    tmp=$(mktemp)
    curl -fsSL -o "$tmp" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    php "$tmp" --info >/dev/null
    sudo install -m 755 "$tmp" /usr/local/bin/wp
    rm -f "$tmp"
fi

echo
echo "Done. Check: curl -sI https://$HOST/  (restart your browser once so it picks up the mkcert CA)"
