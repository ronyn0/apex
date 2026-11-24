<?php

add_filter('openid-connect-generic-plugin-settings', function($settings) {
    return array_merge($settings, [
        'login_endpoint_url'   => 'https://auth.ronyn.cx/application/o/authorize/',
        'endpoint_token'       => 'https://auth.ronyn.cx/application/o/token/',
        'endpoint_userinfo'    => 'https://auth.ronyn.cx/application/o/userinfo/',
        'endpoint_jwks_uri'    => 'https://auth.ronyn.cx/application/o/wordpress/jwks/',
        'endpoint_end_session' => 'https://auth.ronyn.cx/application/o/wordpress/end-session/',
        'client_id'            => OIDC_CLIENT_ID,
        'client_secret'        => OIDC_CLIENT_SECRET,
        'redirect_uri'         => env('WP_HOME') . '/wp/wp-admin/admin-ajax.php?action=openid-connect-authorize',
        'scope'                => 'openid email profile groups',
        'identity_key'         => 'preferred_username',
        'link_existing_users'  => 1,
        'create_if_does_not_exist' => 1,
        'enforce_privacy'      => 0,
        'redirect_user_back'   => 1,
        'iss'                  => 'https://auth.ronyn.cx/application/o/wordpress/',
        'expected_iss'         => 'https://auth.ronyn.cx/application/o/wordpress/',
        'enable_logging'       => 1,
        'log_limit'            => 1000,
        'allow_trust_email'    => 1,
        'displayname_format'   => '{name}',
    ]);
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