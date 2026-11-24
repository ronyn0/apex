<?php
/**
 * Error page body. Expects $args from apex_error_args().
 *
 * @var array{code: int, title: string, message: string, retry: bool} $args
 */
?>
<section class="error-page">
    <p class="error-page__code"><?php echo esc_html((string) $args['code']); ?></p>
    <h1 class="error-page__title"><?php echo esc_html($args['title']); ?></h1>
    <p class="error-page__message"><?php echo esc_html($args['message']); ?></p>

    <div class="error-page__actions">
        <?php if ($args['retry']) : ?>
            <button type="button" class="button" onclick="location.reload()"><?php esc_html_e('Try again', 'apex'); ?></button>
        <?php endif; ?>
        <a class="button<?php echo $args['retry'] ? ' button--ghost' : ''; ?>" href="<?php echo esc_url(home_url('/')); ?>">
            <?php esc_html_e('Go to the blog', 'apex'); ?>
        </a>
    </div>

    <?php if ($args['code'] === 404) : ?>
        <?php get_search_form(); ?>
    <?php endif; ?>
</section>
