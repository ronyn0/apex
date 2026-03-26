<?php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('astra-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('apex-child', get_stylesheet_uri(), ['astra-parent']);
});

add_filter('admin_bar_menu', function($wp_admin_bar) {
    $user = wp_get_current_user();
    $wp_admin_bar->add_node([
        'id'    => 'my-account',
        'title' => $user->display_name,
    ]);
}, 25);