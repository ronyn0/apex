<?php

require_once __DIR__ . '/inc/error-pages.php';

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('automatic-feed-links');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets',
    ]);

    // Make the block editor look like the front end.
    add_theme_support('editor-styles');
    add_editor_style('style.css');

    register_nav_menus([
        'primary' => __('Primary', 'apex'),
    ]);
});

add_action('wp_enqueue_scripts', function () {
    // filemtime() busts browser caches whenever the stylesheet changes on deploy.
    wp_enqueue_style('apex', get_stylesheet_uri(), [], (string) filemtime(get_stylesheet_directory() . '/style.css'));

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
});

add_filter('excerpt_length', fn() => 40);
add_filter('excerpt_more', fn() => '…');

/**
 * Show just the display name in the admin bar instead of "Howdy, Name".
 */
add_action('admin_bar_menu', function (WP_Admin_Bar $bar) {
    $node = $bar->get_node('my-account');
    if ($node) {
        $bar->add_node([
            'id'    => 'my-account',
            'title' => str_replace(sprintf(__('Howdy, %s'), ''), '', $node->title),
        ]);
    }
}, 9999);
