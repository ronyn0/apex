# Changelog

Notable changes to this site. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and releases use [Semantic Versioning](https://semver.org/). Each release is a tagged merge to `main`, which is a production deploy. Changes from before the first tagged release are grouped by deploy date.

## [Unreleased]

### Changed
- WordPress core upgraded from 6.9.12 to **7.1.5**. It needs a database upgrade (`wp core update-db`, schema 60717 → 61833).
- The home page now lists the latest posts, with the site tagline as a short intro line, instead of the Astra starter-template demo page.

## [1.0.0] - 2026-11-23

The first tagged release: a custom theme, themed error pages for the other apps behind Nginx Proxy Manager, working SSO configuration, and clean permalinks.

### Added
- **Apex theme** (`web/app/themes/apex`): a minimal dark classic theme for blog posts that replaces the Astra child theme. It has no parent theme and no upsell notices.
- **Themed error pages** at `/error/<code>/`. Nginx Proxy Manager serves them for other apps' 502/503/504 errors (set up per proxy host in the Advanced tab; see the theme README). Each page returns the real HTTP status and has a **Try again** button that reloads the app.
- `bin/setup-local.sh`: sets up a local Apache, PHP-FPM, MariaDB and HTTPS environment on Ubuntu.
- Project README covering local setup, SSO, the production server and deployment, plus this changelog.

### Changed
- Permalinks are now `/%postname%/` instead of `/index.php/%year%/%monthnum%/%day%/%postname%/`. Apache on the production server now rewrites URLs (`apex-rewrite.conf`; see README, "Production server"). Old `/index.php/` page links redirect, but old dated post links return 404.
- SSO: users must be in a mapped lldap group (`wordpress_admins`, `wordpress_editors`, `wordpress_authors`) to log in. Their role is re-synced from groups on every login, so demotions take effect. Previously, users with no groups kept their old role.
- SSO: the plugin's debug log is now on only in development.
- The admin-bar "Howdy" removal moved into the Apex theme. It now edits the admin bar directly instead of filtering every translated string.

### Fixed
- SSO settings in `oidc-config.php` were never applied: they used a filter name the plugin doesn't have, and wrong setting keys. They now use `openid-connect-generic-settings` with the plugin's real keys. The code also called an undefined `env()` function, which would have crashed if it had run.

## 2026-11-22 (PR #4)

### Added
- Dependabot: daily Composer update PRs against `dev`, grouped into WordPress core and plugins/themes.

### Security
- WordPress core updated to 6.9.12 (wp2shell fix).

### Changed
- Astra is no longer committed; Composer installs it.
- Deploys now come from a GitHub webhook on pushes to `main`.

## 2026-05-17 (PR #3)

### Changed
- Updated the Astra theme.

## 2026-04-19 (PR #2)

### Changed
- Reconfigured SSO, and added support for a local development environment.

## 2026-04-19 (PR #1)

### Added
- Authentik single sign-on with the OpenID Connect Generic plugin, mapping lldap groups to WordPress roles.

## 2026-03-24 – 2026-03-26 (initial setup, committed directly to `main`)

### Added
- Bedrock project setup.
- Apex child theme for Astra, with dark styling.

### Changed
- Removed "Howdy" from the admin bar.
- The deploy webhook script (`web/deploy.php`) is gitignored, so it stays on the server only.

[Unreleased]: https://github.com/ronyn0/apex/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ronyn0/apex/releases/tag/v1.0.0
