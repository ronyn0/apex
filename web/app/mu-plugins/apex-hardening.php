<?php

/**
 * Close the non-SSO ways into WordPress.
 *
 * Everyone logs in through Authentik SSO, so nothing needs XML-RPC or
 * application passwords. Both accept a user's local WordPress password and
 * are what automated tools use to post to a site.
 */

// XML-RPC: no methods at all (pingbacks included), and stop advertising it.
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', fn() => []);
remove_action('wp_head', 'rsd_link');
add_filter('wp_headers', function (array $headers) {
    unset($headers['X-Pingback']);

    return $headers;
});

// Application passwords (REST API / XML-RPC logins with a generated password).
add_filter('wp_is_application_passwords_available', '__return_false');
