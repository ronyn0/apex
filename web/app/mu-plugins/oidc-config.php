<?php

/**
 * Authentik OIDC settings, kept in git so every environment matches.
 * These override anything saved on Settings → OpenID Connect Client.
 *
 * client_id, client_secret and issuer come from the OIDC_* constants in
 * config/application.php (read from .env); the plugin applies those itself.
 * The redirect URI is computed by the plugin from WP_SITEURL:
 *   <WP_SITEURL>/wp-admin/admin-ajax.php?action=openid-connect-authorize
 */
add_filter('openid-connect-generic-settings', function ($settings) {
    $values = [
        'endpoint_login'           => 'https://auth.ronyn.cx/application/o/authorize/',
        'endpoint_token'           => 'https://auth.ronyn.cx/application/o/token/',
        'endpoint_userinfo'        => 'https://auth.ronyn.cx/application/o/userinfo/',
        'endpoint_jwks'            => 'https://auth.ronyn.cx/application/o/wordpress/jwks/',
        'endpoint_end_session'     => 'https://auth.ronyn.cx/application/o/wordpress/end-session/',
        'scope'                    => 'openid email profile groups',
        'identity_key'             => 'preferred_username',
        'link_existing_users'      => 1,
        'create_if_does_not_exist' => 1,
        'enforce_privacy'          => 0,
        'redirect_user_back'       => 1,
        'displayname_format'       => '{name}',
        // Logs are stored in the database; only keep them while developing.
        'enable_logging'           => WP_ENV === 'development' ? 1 : 0,
        'log_limit'                => 1000,
    ];

    foreach ($values as $key => $value) {
        $settings->$key = $value;
    }

    return $settings;
});

/**
 * Resolve the WordPress role for an OIDC claim's Authentik/lldap groups.
 * Returns null when the user is in none of the mapped groups.
 */
function apex_oidc_role_from_claim(array $user_claim): ?string
{
    $groups = is_array($user_claim['groups'] ?? null) ? $user_claim['groups'] : [];

    // lldap group => WP role, highest privilege first.
    $role_map = [
        'wordpress_admins'  => 'administrator',
        'wordpress_editors' => 'editor',
        'wordpress_authors' => 'author',
    ];

    foreach ($role_map as $group => $role) {
        if (in_array($group, $groups, true)) {
            return $role;
        }
    }

    return null;
}

/**
 * Refuse SSO login (and account creation) for users in no mapped group.
 * A missing groups claim is treated the same as "no groups".
 */
add_filter('openid-connect-generic-user-login-test', function($allowed, $user_claim) {
    return $allowed && apex_oidc_role_from_claim((array) $user_claim) !== null;
}, 10, 2);

/**
 * Sync the WordPress role from groups on every SSO login, so demotions in
 * lldap take effect at next login.
 */
add_action('openid-connect-generic-update-user-using-current-claim', function($user, $user_claim) {
    $role    = apex_oidc_role_from_claim((array) $user_claim);
    $wp_user = new WP_User($user->ID);

    // Login test should already have blocked this; strip all roles defensively.
    if ($role === null) {
        $wp_user->set_role('');
        return;
    }

    if ($wp_user->roles !== [$role]) {
        $wp_user->set_role($role);
    }
}, 10, 2);