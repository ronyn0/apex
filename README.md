# Apex

A personal WordPress blog at **https://ronyn.cx**, built on [Bedrock](https://roots.io/bedrock/). Composer manages WordPress core, plugins and themes. Logins go through Authentik single sign-on, and Authentik/lldap group membership sets each user's WordPress role. The custom **Apex** theme also serves themed error pages for the other apps behind the reverse proxy.

- **WordPress** 7.1 (`roots/wordpress`), PHP ≥ 8.3
- **Theme:** [`web/app/themes/apex`](web/app/themes/apex/), a minimal dark classic theme ([theme README](web/app/themes/apex/README.md)). Twenty Twenty-Five is installed as a fallback.
- **SSO:** [OpenID Connect Generic](https://wordpress.org/plugins/daggerhart-openid-connect-generic/) plugin, configured in [`web/app/mu-plugins/oidc-config.php`](web/app/mu-plugins/oidc-config.php)
- **Deploys:** a GitHub webhook pulls `main` onto the server
- **Updates:** Dependabot opens Composer update PRs against `dev` every day

## Repository layout

```
config/
  application.php          Base (production) config, read from .env
  environments/            Overrides per WP_ENV (development, staging)
web/                       Document root
  app/
    mu-plugins/            Always-on plugins (tracked: single-file ones)
      oidc-config.php      SSO settings and group → role mapping
    plugins/               Composer-installed (gitignored)
    themes/apex/           The site theme (tracked)
    uploads/               Media (gitignored)
  wp/                      WordPress core, Composer-installed (gitignored)
  deploy.php               Deploy webhook, server-only (gitignored)
bin/setup-local.sh         Local Apache/PHP/MariaDB setup (Ubuntu)
```

Everything Composer installs (`vendor/`, `web/wp/`, plugins, third-party themes) is gitignored. `composer.lock` decides what gets installed.

## Configuration (`.env`)

Copy `.env.example` to `.env` and fill it in. The file is gitignored; keep it `chmod 600`.

| Variable | Notes |
| --- | --- |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST` | Database connection |
| `WP_ENV` | `development`, `staging` or `production` (picks `config/environments/*.php`) |
| `WP_HOME`, `WP_SITEURL` | Site URL; `WP_SITEURL` is `${WP_HOME}/wp` |
| `AUTH_KEY` … `NONCE_SALT` | Unique random values per environment ([generator](https://roots.io/salts.html)) |
| `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET` | From the WordPress provider in Authentik |
| `OIDC_ISSUER` | `https://auth.ronyn.cx/application/o/wordpress/` |

Production (no `WP_ENV`, or `production`) blocks file edits and plugin installs from the dashboard. Add or update plugins with Composer instead.

## Local development

Assumes Ubuntu with PHP 8.5 and Composer.

1. Install dependencies, then create and fill in `.env`:
   ```sh
   composer install
   cp .env.example .env   # then edit it; use WP_HOME='https://ronyn.test'
   ```
2. Point the hostname at your machine, in `/etc/hosts`:
   ```
   127.0.0.1 ronyn.test
   ```
3. Set up Apache, PHP-FPM, MariaDB and a trusted HTTPS certificate. Run the script as yourself; it uses `sudo` when it needs to:
   ```sh
   bin/setup-local.sh
   ```
   PHP runs as your user, so uploads and caches write straight into the repo. Restart your browser once afterwards, so it trusts the local certificate.
4. Create the database tables and admin user, then switch on the theme and SSO:
   ```sh
   wp core install --url=https://ronyn.test --title="Apex" --admin_user=<you> --admin_email=<email> --prompt=admin_password
   wp theme activate apex
   wp plugin activate daggerhart-openid-connect-generic
   ```

`wp core install` only sets up the database. Composer has already installed the WordPress files, so never use `wp core download` or `wp core update` here.

Lint and test:

```sh
composer lint       # Laravel Pint (composer lint:fix to fix)
composer test       # Pest
```

Logs: `web/app/debug.log` (PHP, in development) and `/var/log/apache2/ronyn.test-error.log`.

## Single sign-on

Users log in with **Login with OpenID Connect** on `wp-login.php`. Authentik is the identity provider, and lldap holds the users and groups.

- The plugin reads `client_id`, `client_secret` and `issuer` from the `OIDC_*` constants. Everything else (endpoints, scopes, user linking) is set in `oidc-config.php` and overrides anything saved on **Settings → OpenID Connect Client**.
- **Redirect URI.** Each environment's URL must be listed in the Authentik provider:
  - `https://ronyn.cx/wp/wp-admin/admin-ajax.php?action=openid-connect-authorize` (production)
  - `https://ronyn.test/wp/wp-admin/admin-ajax.php?action=openid-connect-authorize` (local)
- **Roles come from groups and are re-applied on every login:**

  | lldap group | WordPress role |
  | --- | --- |
  | `wordpress_admins` | Administrator |
  | `wordpress_editors` | Editor |
  | `wordpress_authors` | Author |

  Users in none of these groups can't log in through SSO, and no account is created for them.
- **Removing access.** Taking someone out of a group applies at their next login. Their current session stays valid for up to 2 days. To cut access right away, deactivate the user or revoke their sessions in Authentik, and use **Log Out Everywhere** on their WordPress profile.
- The plugin keeps a debug log (**Settings → OpenID Connect Client → Logs**) in development only.
- **No other ways to log in.** [`apex-hardening.php`](web/app/mu-plugins/apex-hardening.php) switches off XML-RPC and application passwords. The normal `wp-login.php` form still works with each user's local password, for emergencies; keep those passwords long and unused.

If login fails with `invalid_client`, the client secret in `.env` doesn't match Authentik.

## Production server

ronyn.cx runs on `debncsvr01` (Debian, Apache 2.4, PHP). Nginx Proxy Manager sits in front, handles HTTPS, and passes requests to Apache on port 80.

| What | Where |
| --- | --- |
| Checkout (owned by `www-data`) | `/var/www/apex`, document root `/var/www/apex/web` |
| Site config | `/etc/apache2/sites-enabled/ronyn.cx.conf`: takes real client IPs from NPM, sends `.well-known` CalDAV/CardDAV requests to Nextcloud, sets HSTS |
| Permalink rewrites | `/etc/apache2/conf-available/apex-rewrite.conf` (switched on with `a2enconf apex-rewrite`) |
| Nextcloud | `/var/www/nextcloud`, served at `/nextcloud/` by `sites-enabled/nextcloud.conf` |
| Logs | `/var/log/apache2/ronyn-access.log`, `ronyn-error.log`, and `journalctl -t deploy-apex` |

Permalinks are `/%postname%/`. The rewrite rules live in Apache's config rather than `.htaccess`, which is gitignored and which WordPress may overwrite:

```apache
<Directory /var/www/apex/web>
    RewriteEngine On
    # Leave Nextcloud (/nextcloud) to its own rules
    RewriteRule ^nextcloud(/|$) - [L]
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
</Directory>
```

After changing Apache config, run `sudo apache2ctl configtest && sudo systemctl reload apache2`. Then check that `https://ronyn.cx/nextcloud/status.php` still responds.

## Branches and deployment

All work happens on **`dev`**. When something is ready, open a PR from `dev` to **`main`**. Merging to `main` deploys to production automatically.

```
dev ──PR──▶ main ──push──▶ GitHub webhook ──▶ web/deploy.php ──▶ /usr/local/bin/deploy-apex
```

- **GitHub webhook:** repo **Settings → Webhooks**, sends `push` events as `application/json` to `https://ronyn.cx/deploy.php`, signed with a secret.
- **`web/deploy.php`** lives only on the server; it's gitignored. It:
  - rejects requests unless the `X-Hub-Signature-256` HMAC matches the secret in `/var/www/.deploy-secret`;
  - answers GitHub's `ping` event;
  - ignores everything except pushes to `refs/heads/main`;
  - starts `/usr/local/bin/deploy-apex` in the background and returns `202` straight away.
- **`/usr/local/bin/deploy-apex`** (server-only), in the checkout at `/var/www/apex`, running as `www-data` (it switches to that user with `sudo` if started by anyone else):
  1. takes a lock (`/var/www/.composer/deploy.lock`) so only one deploy runs at a time;
  2. `git fetch origin main`, then `git merge --ff-only origin/main`;
  3. `composer install --no-dev --no-interaction --optimize-autoloader`, which installs WordPress core, plugins and themes from `composer.lock`;
  4. logs `Deployed <commit>`.

### Checking a deploy

- **Server log:** every run, including git and Composer output, goes to the system journal:
  ```sh
  journalctl -t deploy-apex -n 50     # recent runs
  journalctl -t deploy-apex -f        # watch live
  ```
- **GitHub:** **Settings → Webhooks → Recent Deliveries** shows each delivery's response: `202 Deploy started`, `Ignored` (not a push to `main`), or `403 Unauthorized` (the secrets don't match).
- **To redeploy by hand**, run `deploy-apex` on the server as yourself. It does the git and Composer steps as `www-data`.

### When a deploy doesn't go through

- **`Not possible to fast-forward`:** the server's checkout has commits or changes that aren't on `main`, probably from an edit made directly on the server. Look with `sudo -u www-data git -C /var/www/apex status`, and move those changes into git through `dev` instead.
- **`Deploy already running`:** a second push came in while a deploy was still running, so it was skipped. The skipped commit goes out with the next deploy; to send it now, run `deploy-apex` by hand.

### Manual steps after some deploys

These live in the database, so a deploy doesn't do them. Run them in `/var/www/apex` as the site's user, `sudo -u www-data wp …`, so any files they write are owned by `www-data`:

| Change | Run on the server |
| --- | --- |
| A new plugin was added | `wp plugin activate <name>` |
| Theme switched or URL rules changed | `wp theme activate apex` / `wp rewrite flush` |
| WordPress core update with database changes | `wp core update-db` |

### Updating dependencies

- Dependabot opens grouped PRs against `dev` every day, one group for WordPress core and one for plugins/themes. Merge them into `dev`, test locally, then PR to `main`.
- By hand: `composer update roots/wordpress roots/wordpress-no-content` (core), or `composer require wpackagist-plugin/<slug>` (new plugin). Commit `composer.lock`.
- **A core update to a new major or minor version** (7.1 → 7.2) usually changes the database. Locally, run `wp core update-db` and test. On production, back up first (`sudo -u www-data wp db export - > ~/apex-pre-<version>.sql`), deploy, then run `sudo -u www-data wp core update-db` straight away. Until you do, wp-admin shows a "Database update required" screen.

## Releases

Each deploy that's worth a version gets a git tag and a section in [CHANGELOG.md](CHANGELOG.md), following [Semantic Versioning](https://semver.org/):

| Bump | When |
| --- | --- |
| Patch (1.1.**1**) | Fixes, dependency updates within the same WordPress minor version |
| Minor (1.**2**.0) | New features, WordPress minor/major upgrades, removed plugins or themes |
| Major (**2**.0.0) | Changes that break existing URLs or logins (permalink structure, SSO provider) |

To release:

1. While working on `dev`, add entries under `## [Unreleased]` in the changelog.
2. Rename that section to `## [x.y.z] - YYYY-MM-DD`, add an empty `## [Unreleased]` above it, and update the links at the bottom (`[Unreleased]` compares `vx.y.z...HEAD`; `[x.y.z]` compares the previous tag with `vx.y.z`).
3. Commit on `dev`, then merge to `main`, tag and push:
   ```sh
   git push origin dev
   git checkout main && git pull --ff-only origin main
   git merge --no-ff dev -m "Release vx.y.z"
   git tag -a vx.y.z -m "vx.y.z: <one-line summary>"
   git push origin main && git push origin vx.y.z
   git checkout dev
   ```
4. Do any server steps the changelog lists (`wp core update-db`, plugin activation, and so on), and watch `journalctl -t deploy-apex -f`.

Pushing the tag also reaches the webhook, which answers `Ignored`; only the push to `main` deploys.

## License

Copyright (c) 2026 Ron Hiscock. All rights reserved.

Built on [Bedrock](https://github.com/roots/bedrock), which is MIT-licensed. The Bedrock-derived files (`config/`, `web/index.php`, `web/wp-config.php`, `wp-cli.yml`) keep its notice:

> Copyright (c) Roots Software LLC
>
> Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
