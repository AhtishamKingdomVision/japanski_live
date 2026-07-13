<?php
get_header();
$sku = get_field('product_sku');
$reviews = [];
if ($sku) {
    $store = 'japanskiexperience.com1';
    $apiUrl = 'https://api.reviews.io/product/review?store=' . $store . '&sku=' . $sku . '&per_page=100&verified_only=true&comments_only=true';
    $response = wp_remote_get($apiUrl);
    $responseBody = wp_remote_retrieve_body($response);
    $reviews = json_decode($responseBody);
    $stats = $reviews->stats;
    $price = get_field('price');
    $schemaReviews = [];

    // echo '<pre>';
    // print_r($reviews);
    // echo '<pre>';
    if($reviews){
        foreach ($reviews->reviews->data as $d) {
            $schemaReviews[] = array(
                "@type" => "Review",
                "author" => array(
                    "@type" => "Person",
                    "name" => $d->reviewer->first_name . ' ' . $d->reviewer->last_name,
                ),
                "datePublished" => date("Y-m-d", strtotime($d->date_created)),
                "description" => "",
                "reviewRating" => array(
                    "@type" => "Rating",
                    "bestRating" => "5",
                    "ratingValue" => $d->rating,
                    "worstRating" => "1", 
                    // "reviewCount" => (string)$stats->count,
                ),
                "reviewBody" => $d->review
            );
        }
    }
    
    $schema = array(
        "@context" => "http://schema.org",
        "@type" => "Product",
        "name" => get_the_title(),
        "image" => get_the_post_thumbnail_url(),
        "description" => get_the_content(),
        // "priceRange" => "(we offer a best price guarantee!)",
        // "priceRange" => "From" .$price. "we offer a best price guarantee!",
        "sku" => $sku,
        "brand" => [
            "@type" => "Brand",
            "name" => "RESORT"
        ],
        // "offers" => [
        //     "@type" => "Offer",
        //     "url" => get_the_permalink(),
        //     // "priceCurrency" => "JPY",
        //     // "price" => $price,
        //     // "price" => "Best Price Guarantee!",
        //     // "priceValidUntil" => "2020-11-20",
        //     "itemCondition" => "https://schema.org/NewCondition",
        //     "availability" => "https://schema.org/InStock"
        // ],
    );

    if( $stats->average && $stats->count ){

        $schema["aggregateRating"] = 
        array(
            "@type" => "AggregateRating",
            "bestRating" => "5",
            "ratingValue" => (string)$stats->average,
            "ratingCount" => (string)$stats->count,
            "worstRating" => "1",
            "description" => "Best price guarantee!"
        );
    }

    if( !empty($schemaReviews) ){
        $schema["review"] = $schemaReviews;
    }

    echo '<script type="application/ld+json">' . json_encode($schema) . '</script>';
}
?>

<div class="content-wrapper full-section">

    <?php
        $award_image = get_field('award_image', 'option');
        $award_opt   = get_field('opt', 'option');
        if($award_opt === true){
                echo '<div class="award_badge">';
                    echo wp_get_attachment_image($award_image, 'full');
                echo '</div>'; //award_badge
        }
        // Theme Option Field
        $toggle     = get_field('toggle_form');
        $form_title = get_field('form_title');
        $overlay = get_field('overlay');
        $sin_title = get_the_title();
        $opt_title  = get_field('title_option', 'option');
        $opt_ID     = get_field('form_id_option', 'option');
        $opt_btn    = get_field('learn_more', 'option');
        $opt_text   = get_field('more_text_option', 'option');
        $toggle_off = get_field('toggle_offa', 'option');

        if($toggle_off == true):
            if($toggle == true):
                $form_s = 'form_toggle';
                $hide_form = 'desktop-none';
            else :
                $form_s = '';
                $hide_form = '';
            endif;
        endif;

        // Single Page Field
        $opt_img    = get_field('image_option', 'option');
        $sin_img = get_the_post_thumbnail_url();




        if($form_title == ''){
            $margin = 'space_bottom';
        } else {
            $margin = '';   
        }

        #if($ban_show == false){
        echo '<section class="full-section top_banner" id="top_sec" style="background: url(' . ($sin_img ? $sin_img : $opt_img) . ') no-repeat center/cover;">';

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
        echo '<a class="quote_form desktop-none" href="'. home_url() .'/get-a-quote/">'.$form_title.'</a>';
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
    ?>
    <div class="right">
        <ul class="social-share">
            <?php
                $pinterest = get_field('pinterest', 'option'); 
                if ($pinterest == true) { ?>
            <li class="pinterest"><a href="//pinterest.com/pin/create/button/?url=<?php echo get_permalink(); ?>"
                    target="_blank"><i class="fa fa-pinterest-p" aria-hidden="true"></i></a></li>
            <?php } ?>

            <?php
                $facebook = get_field('facebook', 'option'); 
                if ($facebook == true) { ?>
            <li class="facebook"><a href="//www.facebook.com/sharer/sharer.php?u=<?php echo get_permalink(); ?>"
                    target="_blank"><i class="fa fa-facebook" aria-hidden="true"></i></a></li>
            <?php } ?>

            <?php 
                $twitter = get_field('twitter', 'option'); 
                if ($twitter == true) { ?>
            <li class="twitter"><a
                    href="//twitter.com/intent/tweet?text=<?php the_title(); ?>&amp;url=<?php echo get_permalink(); ?>"
                    target="_blank"><i class="fa fa-twitter" aria-hidden="true"></i></a></li>
            <?php } ?>

            <?php 
                $linkedin = get_field('linkedin', 'option'); 
                if ($linkedin == true) { ?>
            <li class="linkedin"><a href="//www.linkedin.com/sharing/share-offsite/?url=<?php echo get_permalink(); ?>"
                    target="_blank"><i class="fa fa-linkedin" aria-hidden="true"></i></a></li>
            <?php } ?>

            <?php
                $mail = get_field('mail', 'option'); 
                if ($mail == true) { ?>
            <li class="mail"><a href="mailto:?subject=<?php the_title(); ?>&amp;body=<?php echo get_permalink(); ?>"><i
                        class="fa fa-envelope"></i></a></li>
            <?php } ?>
        </ul>
    </div> <!-- right -->
    <?php
        echo '</div>';
        echo '</section>';

        if (get_field('package_builder')) {
            $package = get_field('package_builder');
            foreach ($package as $key => $section) {
                include('package/inc-' . $section['acf_fc_layout'] . '.php');
            }
        } else {
            echo '<div class="container" style="text-align:center;">';
            echo '<h2 style="margin:20px 0 20px;display:inline-block;">';
            echo 'Fields Not Founds';
            echo '</h2>';
            echo '</div>';
        }

        if (!empty($reviews)) {
            $link_url = ' https://www.reviews.io/product-reviews/store/japanskiexperience-com1/' . $sku;
            $link_title = "View All " . get_the_title() . " Reviews";
            $link_target = "blank";

            $stats = $reviews->stats;

            $average = number_format($stats->average, 2) ?: '4.8';
            $total = $stats->count ?: '632';
            $count = $average . ' Average ' . $total . ' Reviews';
            $title = get_option('kv_company_review_title') ?: 'Excellent';

            echo '<section id="sectionR" class="full-section client_reviews">';
            echo '<div class="container">';
            echo '<div class="cr_cover">';
            echo '<div class="cr_left">';
            if ($title) {
                printf('<h2>%s</h2>', $title);
            }
            echo '<span class="five-star star-count-' . (int)$average . '"></span>';
            if ($count) {
                printf('<h3>%s</h3>', $count);
            }
            if (!empty($sku)):
                printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title);
            endif;
            echo '</div>'; //cr_left
            echo '<div class="cr_right">';
            if (!empty($reviews->reviews->data) && !is_wp_error($reviews)) {
                $data = $reviews->reviews->data;
                echo ' <div class="reviews-carousel">';
                foreach ($data

                         as $d) {
                    $reviewDate = date("F Y", strtotime($d->date_created));
                    $postedDate = 'Posted ' . $reviewDate;
                    ?>
    <div class="item">
        <div class="title">
            <?= str_replace( "&quot;", "", html_entity_decode( $d->reviewer->first_name . ' ' . $d->reviewer->last_name ) ); ?>&nbsp;&nbsp;
            <i class="fa fa-star"></i>
            <i class="fa fa-star"></i>
            <i class="fa fa-star"></i>
            <i class="fa fa-star"></i>
            <i class="fa fa-star"></i>
        </div>
        <div class="excerpt more"><?= $d->review ?></div>
    </div>
    <?php }
                echo '</div>';
            }
            echo '</div>'; //cr_right
            echo '</div>'; //cr_cover
            echo '</div>';
            echo '</section>';
        }
        ?>
    <style type="text/css">
    <?php if($overlay==true): ?>section.top_banner:after {
        content: "";
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        background: rgb(0 0 0 / 50%);
        z-index: -1;
    }

    <?php endif;
    ?>
    </style>
</div> <!-- content-wrapper -->
<?php get_footer(); ?>