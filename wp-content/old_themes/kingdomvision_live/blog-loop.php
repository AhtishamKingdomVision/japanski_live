<div class="post-wrapper">
  <div class="post-thumbnail"> <?php echo get_the_post_thumbnail( get_the_ID(), 'post_size' );?> </div>
  <div class="post-title">
    <h3><a href="<?php the_permalink(); ?>"> <?php the_title();?> </a></h3>
  </div>
  <div class="post-except"><?php echo wp_trim_words( get_the_content(), 40 ); ?></div>
  <div class="post-more"><a href="<?php the_permalink(); ?>">Read More »</a></div>
</div>
