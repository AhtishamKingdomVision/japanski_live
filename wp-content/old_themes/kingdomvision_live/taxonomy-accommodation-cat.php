<?php get_header();

$term = get_queried_object();

$banner = get_field('category_banner' , $term);
echo '<section class="full-section simple_page_banner_with_title" style="background: url('.($banner ? $banner : wp_get_attachment_url(586)).') no-repeat center/cover;">';
  echo '<div class="container">';
    echo '<h2>'.$term->name.'</h2>';
  echo '</div>';
echo '</section>';

echo '<section class="full-section cs_breadcrumbs">';
    echo '<div class="container">';
        echo do_shortcode('[cst-breadcrumbs]');
    echo '</div>';
echo '</section>';
?>

<section class="full-section blogs_section">
  <div class="container">
      <div class="blog-wrapper">
        <?php 
          $acc = new WP_Query(
            array(
              'post_type' => 'accommodation', 
              'posts_per_page' => -1,
              'tax_query' => array(
                array(
                  'taxonomy' => 'accommodation-cat',
                  'terms' => $term,
                  'field' => $term->slug,
                )
              )
            ));
          while($acc->have_posts()) : $acc->the_post();
          $thumbnail_id = get_post_thumbnail_id( $post->ID );
          $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
            echo '<div class="post-box v1">';
              echo '<a href="'.get_the_permalink().'">';
                echo '<div class="image">';
                    echo wp_get_attachment_image($thumbnail_id, 'blog_size', array( 'alt' => $alt ));
                echo '</div>'; //image
              echo '</a>';
              echo '<div class="cont">';
                echo '<p class="date">'.get_the_time('d, F, Y').'</p>';
                echo '<p class="cat">';
                  $categories = get_terms('accommodation-cat');
                  // print_r($categories);
                  echo '<strong>Categories: </strong>';
                  foreach ($categories as $key => $value) {
                    echo '<a href="' . esc_attr( esc_url( get_category_link( $value->term_id ) ) ) . '" class="cat">'.$value->name.'</a>';
                  }  
                echo '<h2><a href="'.get_the_permalink().'">'.get_the_title().'</a></h2>';
                echo '<p class="author">By '.get_the_author_meta('user_email').'</p>';
                echo '<a class="btn light_green" href="'.get_the_permalink().'">Read More</a>';
              echo '</div>';
            echo '</div>';
          endwhile;
        ?>

        
      
      </div>
  </div> <!-- container -->
</section><!-- content-wrapper -->

<?php get_footer(); ?>