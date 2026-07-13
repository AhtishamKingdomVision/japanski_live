<?php get_header();
$award_image = get_field('award_image', 'option');
$award_opt   = get_field('opt', 'option');
$author    = get_field('post_auther', get_the_ID());
$date      = get_field('post_date', get_the_ID());
// $default   = get_field('default_img', get_the_ID());
// $def_image = get_field('default_imgg', get_the_ID());
$default   = get_field('default_imagep', 'option');
$banner    = get_field('banner_image', get_the_ID());

$toggle     = get_field('toggle_form', get_the_ID());
$form_title = get_field('form_title', get_the_ID());
$overlay    = get_field('overlay', get_the_ID());
$sin_title  = get_the_title();
$opt_title  = get_field('title_option', 'option');
$opt_ID     = get_field('form_id_option', 'option');
$opt_btn    = get_field('learn_more', 'option');
$opt_text   = get_field('more_text_option', 'option');
$toggle_off = get_field('toggle_off', 'option');

if($toggle_off == true):
    if($toggle == true):
        $form_s = 'form_toggle';
        $hide_form = 'desktop-none';
    else :
        $form_s = '';
        $hide_form = '';
    endif;
endif;

if($form_title == ''){
    $margin = 'space_bottom';
} else {
    $margin = '';   
}

if($banner):

    echo '<section class="full-section top_banner" style="background: url('.$banner.') no-repeat center/cover;">';  
 else :
    if($default){
        echo '<section class="full-section top_banner" style="background: url('. $default.') no-repeat center/cover;">';
    } else {
        echo '<section class="full-section top_banner" style="background: url('. wp_get_attachment_image_url(8467 , 'full').') no-repeat center/cover;">';
    }

endif;
if($award_opt === true){
        echo '<div class="award_badge">';
            echo wp_get_attachment_image($award_image, 'full');
        echo '</div>'; //award_badge
}
        echo '<div class="container">';
        if ($sin_title) {
            printf('<h1 class="'.$margin.'">%s</h1>', $sin_title);
            if ($form_title) {
                echo '<div class="'. $form_s .' mobile-none"><a href="javascript:;">' . $form_title . '</a></div>';
            }

        } else {
            printf('<h1 class="'.$margin.'">%s</h1>', $opt_title);
            if ($form_title) {
                echo '<div class="'. $form_s .' mobile-none"><a href="javascript:;">' . $form_title . '</a></div>';
            }
        }
        echo '<a class="quote_form desktop-none" href="'. home_url() .'/get-a-quote/">Get a Quote</a>';
        echo '<div class="enquire_form mobile-none '. $hide_form .'">';

        if ($opt_text):
            echo '<a class="fm_open" href="javascript:;">' . $opt_btn . '</a>';
        endif;
        echo do_shortcode('[gravityform id="' . $opt_ID . '" field_values="static_refer='.get_permalink().'" title="true" description="false" ajax="true"]');
        if ($opt_text):
            echo '<div class="more_text" style="display:none;">';
            echo '<a class="fm_close" href="javascript:;"><i class="fa fa-close"></i></a>';
            echo $opt_text;
            echo '</div>'; //more_text
        endif;
        echo '</div>'; //enquire_form

    echo '</div>'; //container
echo '</section>';

echo '<section class="full-section cs_breadcrumbs">';
    echo '<div class="container">';
        echo do_shortcode('[cst-breadcrumbs]');
    echo '</div>';
echo '</section>';

?>
<div class="content-wrapper full-section">
  <div class='container'>
    <div class="blog-content-area full-section">
              <?php
          if( have_posts() ){ while ( have_posts() ){ the_post();?>
              <div class="post-wrapper">
                  <div class="post-info">
                    <div class="left">
                      <div class="author">
                        <strong>By: </strong><?php
                        if($author){
                            echo $author;
                        } else {
                            echo get_the_author_meta('user_email');
                        }
                        ?>
                      </div> <!-- author -->
                      <div class="date">
                        | <?php 
                        if($date){
                            echo $date;
                        } else {
                            echo get_the_time('d, F, Y');
                        }
                        ?>
                      </div> <!-- date -->
                      <div class="cate">
                        <strong>Categories: </strong>
                        <?php 
                          $cat = get_the_category();
                          foreach ($cat as $key => $value) {
                            echo '<a href="' . esc_attr( esc_url( get_category_link( $value->term_id ) ) ) . '" class="cat">'.$value->name.'</a>';
                          }
                        ?>
                      </div> <!-- cate -->
                    </div> <!-- left -->
                    <div class="right">
                      <ul class="social-share">
                        <?php
                        $pinterest = get_field('pinterest', 'option'); 
                        if ($pinterest == true) { ?>
                            <li class="pinterest"><a href="//pinterest.com/pin/create/button/?url=<?php echo get_permalink(); ?>" target="_blank"><i class="fa fa-pinterest-p" aria-hidden="true"></i></a></li>
                        <?php } ?>

                        <?php
                        $facebook = get_field('facebook', 'option'); 
                        if ($facebook == true) { ?>
                            <li class="facebook"><a href="//www.facebook.com/sharer/sharer.php?u=<?php echo get_permalink(); ?>" target="_blank"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
                        <?php } ?>
                        
                        <?php 
                        $twitter = get_field('twitter', 'option'); 
                        if ($twitter == true) { ?>
                            <li class="twitter"><a href="//twitter.com/intent/tweet?text=<?php the_title(); ?>&amp;url=<?php echo get_permalink(); ?>" target="_blank"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
                        <?php } ?>

                        <?php 
                        $linkedin = get_field('linkedin', 'option'); 
                        if ($linkedin == true) { ?>
                            <li class="linkedin"><a href="//www.linkedin.com/sharing/share-offsite/?url=<?php echo get_permalink(); ?>" target="_blank"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
                        <?php } ?>

                        <?php
                        $mail = get_field('mail', 'option'); 
                        if ($mail == true) { ?>
                            <li class="mail"><a href="mailto:?subject=<?php the_title(); ?>&amp;body=<?php echo get_permalink(); ?>"><i class="fa fa-envelope"></i></a></li>
                        <?php } ?>
                      </ul>
                    </div> <!-- right -->
                  </div> <!-- post-info -->
                  <div class="post-except"><?php the_content(); ?></div> <!-- post-except -->
               </div> <!-- post-wrapper -->
        <?php } }
          ?>
    </div> <!-- post-content -->
    </div> <!-- container --> 
    
    <div class="post_page_builder full-section">
      <?php
        if( get_field('page_builder') ){
          $page_builder = get_field('page_builder');
          foreach ($page_builder as $key => $section) {
            include('inc/inc-'.$section['acf_fc_layout'].'.php');
          }
        } else {
          echo '<div class="container" style="text-align:center;">';
            echo '<h2 style="margin:20px 0 20px;display:inline-block;">';
              // echo 'Fields Not Founds';
            echo '</h2>';
          echo '</div>';  
        } 
      ?>
    </div> <!-- post_page_builder -->

      <div class="blog-content-area full-section">
      <div class="container">
        <?php
        echo '<div class="more_posts">';
         $prev_post = get_previous_post();
         $next_post = get_next_post();
          if(!empty($prev_post) || !empty($next_post)) {
           $one_div = 'full-with';
          }   
            if($prev_post) {
              echo '<div class="prev_posts '.$one_div.'">';
                 $prev_title = strip_tags(str_replace('"', '', $prev_post->post_title));
                 echo "\t" . '<a rel="prev" href="' . get_permalink($prev_post->ID) . '" title="' . $prev_title. '" class=" "><i class="fa fa-long-arrow-left" aria-hidden="true"></i>&nbsp;&nbsp;Previous Post</a>' . "\n";
              echo '</div>';
            }

            if($next_post) {
              echo '<div class="next_posts '.$one_div.'">';
                 $next_title = strip_tags(str_replace('"', '', $next_post->post_title));
                 echo "\t" . '<a rel="next" href="' . get_permalink($next_post->ID) . '" title="' . $next_title. '" class=" ">Next Post&nbsp;&nbsp;<i class="fa fa-long-arrow-right" aria-hidden="true"></i></a>' . "\n";
              echo '</div>';
            }
            echo '</div>';
        ?>
      </div>
      </div>
</div> <!-- content-wrapper -->
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