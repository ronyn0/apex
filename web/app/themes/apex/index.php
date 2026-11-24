<?php
/**
 * Post lists: blog home, archives and search results.
 */

get_header();
?>

<?php if (is_search()) : ?>
    <header class="page-header">
        <?php /* translators: %s: search query */ ?>
        <h1 class="page-title"><?php printf(esc_html__('Results for “%s”', 'apex'), esc_html(get_search_query())); ?></h1>
    </header>
<?php elseif (is_archive()) : ?>
    <header class="page-header">
        <?php the_archive_title('<h1 class="page-title">', '</h1>'); ?>
    </header>
<?php endif; ?>

<?php if (have_posts()) : ?>
    <?php
    while (have_posts()) {
        the_post();
        get_template_part('template-parts/content', 'summary');
    }

    the_posts_pagination([
        'prev_text' => __('← Newer', 'apex'),
        'next_text' => __('Older →', 'apex'),
    ]);
    ?>
<?php else : ?>
    <p><?php esc_html_e('Nothing here yet.', 'apex'); ?></p>
    <?php if (is_search()) {
        get_search_form();
    } ?>
<?php endif; ?>

<?php
get_footer();
