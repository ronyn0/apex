<?php
/**
 * Single posts and pages.
 */

get_header();

while (have_posts()) :
    the_post();
    ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
            <?php if (get_post_type() === 'post') : ?>
                <div class="entry-meta">
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
                    <?php if (get_the_modified_date('Y-m-d') !== get_the_date('Y-m-d')) : ?>
                        · <?php
                        /* translators: %s: date the post was last updated */
                        printf(esc_html__('Updated %s', 'apex'), esc_html(get_the_modified_date()));
                        ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if (has_post_thumbnail()) : ?>
            <figure class="entry-thumbnail"><?php the_post_thumbnail('large'); ?></figure>
        <?php endif; ?>

        <div class="entry-content">
            <?php the_content(); ?>
            <?php wp_link_pages(); ?>
        </div>

        <?php if (has_tag()) : ?>
            <footer class="entry-footer"><?php the_tags('', ', '); ?></footer>
        <?php endif; ?>
    </article>

    <?php
    if (get_post_type() === 'post') {
        the_post_navigation([
            'prev_text' => '← %title',
            'next_text' => '%title →',
        ]);
    }

    if (comments_open() || get_comments_number()) {
        comments_template();
    }
endwhile;

get_footer();
