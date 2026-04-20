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
 * Map Authentik/lldap groups to WordPress roles on every SSO login.
 */
add_action('openid-connect-generic-update-user-using-current-claim', function($user, $user_claim) {
    if (empty($user_claim['groups']) || !is_array($user_claim['groups'])) {
        return;
    }

    $groups = $user_claim['groups'];

    // Map your actual lldap group names to WP roles (highest privilege first)
    $role_map = [
        'wordpress_admins'  => 'administrator',
        'wordpress_editors' => 'editor',
        'wordpress_authors' => 'author',
    ];

    $assigned_role = 'subscriber'; // default for any authenticated user

    foreach ($role_map as $group => $role) {
        if (in_array($group, $groups, true)) {
            $assigned_role = $role;
            break;
        }
    }

    $wp_user = new WP_User($user->ID);
    $wp_user->set_role($assigned_role);

}, 10, 2);