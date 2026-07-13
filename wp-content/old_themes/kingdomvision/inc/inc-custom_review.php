<?php

$title = $section['title'];
$count = $section['rating_count'];
$link = $section['link'];
$reviews = $section['reviews'];

// count($reviews);
// print_r(count($reviews));

echo '<section class="full-section client_reviews">';
    echo '<div class="container">';
        echo '<div class="cr_cover">';
            echo '<div class="cr_left">';
                if ($title) {
                    echo '<h2>'.$title.'</h2>';
                }
                echo '<span class="five-star"></span>';
                if ($count) {
                    echo '<h3>'.$count.'</h3>';
                }
                if ($link):
                    $link_url = $link['url'];
                    $link_target = $link['target'] ? $link['target'] : '_self' ;
                    $link_title = $link['title'];
                    echo '<a class="btn light_green" href="'.esc_url($link_url).'" target="'.esc_attr($link_target).'">'.esc_html($link_title).'</a>';
                endif;
            echo '</div>'; //cr_left

            echo '<div class="cr_right">';
                if($reviews){
                    echo '<div class="reviews-carousel">';
                        foreach( $reviews as $post ){
                            setup_postdata($post);
                            echo '<div class="item">';
                                echo '<div class="title">';
                                    $post_title = get_the_title();
                                    $string = str_replace("&amp;quot;", "", $post_title);
                                    echo $string;
                                    echo '&nbsp;&nbsp;';
                                    $rating = get_post_meta(get_the_ID(), 'review_rating', 1) ?: 5;
                                    for ($i = 1; $i <= $rating; $i++) {
                                        echo '<i class="fa fa-star"></i>';
                                    }
                                echo '</div>'; #title
                                echo '<div class="excerpt more">';
                                    echo get_the_content();
                                echo '</div>'; #excerpt
                                echo '<span>Posted '.get_the_date('F, Y').'</span>';
                            echo '</div>'; #item
                        }
                    wp_reset_postdata();
                    echo '</div>';
                }
            echo '</div>'; //cr_right
        echo '</div>'; //cr_cover
    echo '</div>';
echo '</section>';
?>