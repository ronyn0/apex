<?php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('astra-parent', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('apex-child', get_stylesheet_uri(), ['astra-parent']);
});
