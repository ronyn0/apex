<?php

/**
 * Themed error pages.
 *
 * /error/<code>/ renders a styled page with that HTTP status, so the reverse
 * proxy can show it when another app is down or erroring. The same content
 * is used for WordPress's own 404s (see 404.php).
 */

/**
 * Status codes the error route serves, with the copy shown for each.
 * 'retry' adds a "Try again" button (useful when an app is restarting).
 *
 * @return array<int, array{title: string, message: string, retry: bool}>
 */
function apex_error_codes(): array
{
    return [
        400 => [
            'title'   => __('Bad request', 'apex'),
            'message' => __('The request couldn’t be understood. Check the address and try again.', 'apex'),
            'retry'   => false,
        ],
        401 => [
            'title'   => __('Sign-in required', 'apex'),
            'message' => __('You need to sign in to see this page.', 'apex'),
            'retry'   => false,
        ],
        403 => [
            'title'   => __('Access denied', 'apex'),
            'message' => __('Your account doesn’t have access to this page.', 'apex'),
            'retry'   => false,
        ],
        404 => [
            'title'   => __('Page not found', 'apex'),
            'message' => __('There’s nothing at this address. It may have moved or never existed.', 'apex'),
            'retry'   => false,
        ],
        500 => [
            'title'   => __('Something went wrong', 'apex'),
            'message' => __('The app hit an error while handling the request.', 'apex'),
            'retry'   => true,
        ],
        502 => [
            'title'   => __('App unavailable', 'apex'),
            'message' => __('The app behind this address isn’t responding. It may be restarting or turned off.', 'apex'),
            'retry'   => true,
        ],
        503 => [
            'title'   => __('Temporarily unavailable', 'apex'),
            'message' => __('This app is down for maintenance or still starting up. Try again in a minute.', 'apex'),
            'retry'   => true,
        ],
        504 => [
            'title'   => __('App timed out', 'apex'),
            'message' => __('The app took too long to respond. It may be overloaded or starting up.', 'apex'),
            'retry'   => true,
        ],
    ];
}

/**
 * Template args for one code; unknown codes fall back to a generic message.
 *
 * @return array{code: int, title: string, message: string, retry: bool}
 */
function apex_error_args(int $code): array
{
    $codes = apex_error_codes();

    return ['code' => $code] + ($codes[$code] ?? [
        'title'   => __('Something went wrong', 'apex'),
        'message' => __('The request couldn’t be completed.', 'apex'),
        'retry'   => $code >= 500,
    ]);
}

add_action('init', function () {
    add_rewrite_rule('^error/([0-9]{3})/?$', 'index.php?apex_error=$matches[1]', 'top');
});

add_filter('query_vars', function (array $vars) {
    $vars[] = 'apex_error';

    return $vars;
});

// Register/remove the /error/ rule when the theme is switched on or off.
add_action('after_switch_theme', 'flush_rewrite_rules');
add_action('switch_theme', 'flush_rewrite_rules');

/**
 * Serve the error page. Runs before canonical redirects so the proxy always
 * gets the page itself, never a 301 to it.
 */
add_action('template_redirect', function () {
    $code = (int) get_query_var('apex_error');
    if ($code === 0) {
        return;
    }

    // Only serve real error statuses; anything else is a plain 404.
    if ($code < 400 || $code > 599) {
        $code = 404;
    }

    status_header($code);
    nocache_headers();
    if (in_array($code, [502, 503, 504], true)) {
        header('Retry-After: 30');
    }

    add_filter('wp_robots', 'wp_robots_no_robots');
    $args = apex_error_args($code);
    add_filter('pre_get_document_title', fn() => sprintf('%d · %s', $code, $args['title']));

    get_header();
    get_template_part('template-parts/error', null, $args);
    get_footer();
    exit;
}, 1);
