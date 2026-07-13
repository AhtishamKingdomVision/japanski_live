<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php bloginfo('name'); ?><?php wp_title(); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<meta name="facebook-domain-verification" content="btj7aopevat0eiv4h31e4oddsmijky" />
    <meta name="p:domain_verify" content="aadb3f17a72d5df87b66dd70aba9a515"/>
    <?php
    $favicon = get_field('favicon_image', 'option');
    if ($favicon) {
        echo '<link rel="shortcut icon" href="' . $favicon . '" type="image/x-icon" />';
    } else {
        echo '<link rel="shortcut icon" href="' . get_stylesheet_directory_uri() . '/images/favicon.png" type="image/x-icon" />';
    }
    ?>

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-5T6M9MFT');</script>
    <!-- End Google Tag Manager -->

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <!-- <script async src="https://www.googletagmanager.com/gtag/js?id=UA-17187209-5"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){window.dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', 'UA-17187209-5');
        </script> -->
    <?php
        require_once 'get_company_reviews.php';
        $schema = hz_reviewsio_company_schema();
    ?>

    <?php 
    wp_head();
    echo $schema;

    $vote_option = get_field('vote_option', 'option');
    if($vote_option == true){
    ?>
    <script>
        jQuery('body').addClass('voter_onn');
    </script>
    <?php
    }
    ?>
	
</head>
<body <?php body_class(); ?>>

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5T6M9MFT"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<!--Main Wrapper-->
<div class="main-wrapper full-section">
    <!--Header-->
    <header class="header-wrapper full-section desktop-header">
        <div class="navigation-wrapper">
            <div class="container">
                <?php
                if (has_nav_menu('main-menu')) {
                    wp_nav_menu(array(
                        'theme_location' => 'main-menu',
                        'menu_class' => 'cus-menu'
                    ));
                }
                ?>
            </div>
        </div> <!-- navigation-wrapper -->

        <?php

        if (has_nav_menu('second-menu')) {
            echo '<div class="second-menu-wrapper">';
            echo '<div class="container">';
            wp_nav_menu(
                array(
                    'theme_location' => 'second-menu',
                    'menu_class' => 'cus-second-menu',
                )
            );
            echo '</div>';
            echo '</div>';
        }

        ?>

    </header>
    <?php include('mobile-header.php'); ?>
    <div class="searchbox">
        <a href="javascript:;" class="close"><i class="fa fa-times" aria-hidden="true"></i></a>
        <div class="inner_wrapper">
            <h2>search</h2>
            <form method="get" id="searchform" action="<?php echo home_url(); ?>/">
                <div class="search-text">
                    <input type="text" placeholder="Search" value="<?php the_search_query(); ?>" name="s" id="s"
                           autocomplete="off"/>
                    <input type="submit" id="searchsubmit" value=""/>
                </div>
            </form>
        </div>
    </div>
