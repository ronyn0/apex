<article id="post-<?php the_ID(); ?>" <?php post_class('post-summary'); ?>>
    <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
    <div class="entry-meta">
        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
    </div>
    <div class="entry-summary"><?php the_excerpt(); ?></div>
</article>
