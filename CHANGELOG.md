# Changelog

Notable changes to this site. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Each section is a merge to `main`, which is a production deploy. The site isn't versioned, so sections are dated.

## [Unreleased]

### Added
- **Apex theme** (`web/app/themes/apex`): a minimal dark classic theme for blog posts that replaces the Astra child theme. It has no parent theme and no upsell notices.
- **Themed error pages** at `/error/<code>/`, for the reverse proxy to show when other apps are down or erroring. Each page returns the real HTTP status. Setup is in the theme README.
- `bin/setup-local.sh`: sets up a local Apache, PHP-FPM, MariaDB and HTTPS environment on Ubuntu.
- Project README covering setup, SSO and deployment, plus this changelog.

### Changed
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
