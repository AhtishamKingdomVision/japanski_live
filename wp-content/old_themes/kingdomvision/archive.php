<?php get_header();
$term   = get_queried_object();
$tag_line = get_field('tag_line' , $term);
$banner = get_field('category_banner' , $term);
$overlay = get_field('banner_overlay' , $term);

$opt_ID     = get_field('form_id_option', 'option', $term);
$cat_banner = get_field('category_banner_image', 'option', $term);
$cat_btn    = get_field('category_btn', 'option', $term);
$cat_toggle = get_field('category_toggle', 'option', $term);

if($cat_toggle == true):
    $form_s = 'form_toggle';
    $hide_form = 'desktop-none';
else :
    $form_s = '';
    $hide_form = '';
endif;

echo '<section class="full-section top_banner" style="background: url('.($banner ? $banner : wp_get_attachment_url(586)).') no-repeat center/cover;">';
  echo '<div class="container">';
    echo '<h2>'.$term->name.'</h2>';
    if($tag_line){ printf('<p class="space_bottom">%s</p>', $tag_line); }

        if ($cat_btn):
            echo '<div class="'. $form_s .' mobile-none"><a href="javascript:;">' . $cat_btn . '</a></div>';
        else :
            echo '<div class="'. $form_s .' mobile-none"><a href="javascript:;">Get a Quote</a></div>';
        endif;

        echo '<a class="quote_form desktop-none" href="'. home_url() .'/get-a-quote/">'. $cat_btn .'</a>';
        echo '<div class="enquire_form mobile-none '. $hide_form .'">';
            echo do_shortcode('[gravityform id="' . $opt_ID . '" title="true" description="false" ajax="true"]');
        echo '</div>'; //enquire_form
  echo '</div>';
echo '</section>';

echo '<section class="full-section cs_breadcrumbs">';
    echo '<div class="container">';
        echo do_shortcode('[wpseo_breadcrumb]');
    echo '</div>';
    the_archive_description( '<div class="taxonomy-description">', '</div>' );
echo '</section>';
?>

<section class="full-section blogs_section">
  <div class="container">
      <div class="blog-wrapper">
        <?php 
          while(have_posts()) : the_post();
          $size = '360x250';
          $thumbnail_id = get_post_thumbnail_id( $post->ID );
          $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
          // print_r($thumbnail_id);
            echo '<div class="post-box">'; 
              echo '<a href="'.get_the_permalink().'">';
                // echo '<div class="image" style="background: url('. get_the_post_thumbnail_url() .') no-repeat center/cover;"></div>';
              echo '<div class="image">';
                echo wp_get_attachment_image($thumbnail_id, 'blog_size', array( 'alt' => $alt ));
                // echo '<img src="'. get_the_post_thumbnail_url($post->ID, 'blog_size', array( 'alt' => $alt )) .'">';
              echo '</div>'; //image
              echo '</a>';
              echo '<div class="cont">';
                    $date = get_field('post_date');
                    if ($date) {
                        echo '<p class="date">' . $date . '</p>';
                    } else {
                        echo '<p class="date">' . get_the_time('d, F, Y') . '</p>';
                    }
                $cate = get_the_category();
                echo '<p class="cat">';
                  echo '<strong>Categories: </strong>';
                  foreach ($cate as $key => $value) {
                    echo '<a href="' . esc_attr( esc_url( get_category_link( $value->term_id ) ) ) . '" class="cat">'.$value->name.'</a>';
                  }                
                echo '<h2><a href="'.get_the_permalink().'">'.get_the_title().'</a></h2>';
                $author = get_field('post_auther');
                if ($author) {
                    echo '<p class="author">By ' . $author . '</p>';
                } else {
                    echo '<p class="author">By ' . get_the_author_meta('user_email',get_the_author_ID()) . '</p>';
                }
                echo '<a class="btn light_green" href="'.get_the_permalink().'">View Snow Report</a>';
              echo '</div>';
            echo '</div>';
          endwhile;
        ?>

        
      
      </div>
  </div> <!-- container -->
</section><!-- content-wrapper -->
<?php if($overlay == true): ?>
    <style type="text/css">
        section.top_banner:after {
            content: "";
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            background: rgb(0 0 0 / 50%);
            z-index: -1;
        }
    </style>
<?php endif; ?>
<?php get_footer(); ?>