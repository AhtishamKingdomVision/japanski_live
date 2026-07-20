<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php wp_title(); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <?php
    $favicon = get_field('favicon_image', 'option');
    if ($favicon) {
        echo '<link rel="shortcut icon" href="' . $favicon . '" type="image/x-icon" />';
    } else {
        echo '<link rel="shortcut icon" href="' . get_stylesheet_directory_uri() . '/images/favicon.png" type="image/x-icon" />';
    }

    wp_head(); ?>
    <style id="kv-page-loader-critical">
        #kv-page-loader{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:radial-gradient(ellipse at 50% 35%,#0a2740 0%,#01111f 70%);transition:opacity .4s ease,visibility .4s ease}
        #kv-page-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
        #kv-page-loader .kv-page-loader-inner{display:flex;flex-direction:column;align-items:center;gap:28px;padding:24px;text-align:center}
        #kv-page-loader .kv-page-loader-logo{width:min(220px,55vw);height:auto;opacity:0;animation:kvLoaderLogoIn .6s ease forwards,kvLoaderLogoPulse 2s ease-in-out .6s infinite}
        #kv-page-loader .kv-page-loader-bar{width:120px;height:3px;border-radius:999px;background:rgba(255,255,255,.12);overflow:hidden}
        #kv-page-loader .kv-page-loader-bar>span{display:block;height:100%;width:40%;border-radius:999px;background:linear-gradient(90deg,transparent,#fff 40%,#fff 60%,transparent);animation:kvLoaderBar 1.1s ease-in-out infinite}
        #kv-page-loader .kv-page-loader-text{margin:0;color:rgba(255,255,255,.65);font-size:13px;font-weight:500;letter-spacing:.12em;text-transform:uppercase;font-family:inherit}
        @keyframes kvLoaderLogoIn{from{opacity:0;transform:translateY(8px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
        @keyframes kvLoaderLogoPulse{0%,100%{opacity:1}50%{opacity:.82}}
        @keyframes kvLoaderBar{0%{transform:translateX(-120%)}100%{transform:translateX(320%)}}
    </style>
</head>
<body <?php body_class(); ?>>

<?php
$logo          = get_field('logo', 'option');
$search        = get_field('search', 'option');
$buttons       = get_field('header_buttons', 'option');
$header_filter = get_field('header_filter', 'option');
$header_option = get_field('header_option');

echo '<div id="kv-page-loader" aria-hidden="true">';
    echo '<div class="kv-page-loader-inner">';
        if ($logo) {
            echo wp_get_attachment_image($logo, 'medium', false, array(
                'class' => 'kv-page-loader-logo',
                'alt'   => esc_attr(get_bloginfo('name')),
            ));
        } else {
            echo '<div class="kv-page-loader-logo" style="width:48px;height:48px;border-radius:50%;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;animation:kvLoaderBar 1s linear infinite;background:transparent"></div>';
        }
        echo '<div class="kv-page-loader-bar" aria-hidden="true"><span></span></div>';
        echo '<p class="kv-page-loader-text">Loading experience</p>';
    echo '</div>';
echo '</div>';
echo '<noscript><style>#kv-page-loader{display:none!important}</style></noscript>';

echo '<div class="main_wrapper full-section">';
    
    // Main Wrapper
    // if($header_option == 'colored'):
        // echo '<div class="header_space"></div>';
    // endif;

    echo '<header class="newHeader '.esc_attr($header_option).'">';
        echo '<div class="topHeader">';
            echo '<div class="container">';
                echo '<div class="logoWrap" aria-label="Go to homepage">';
                    echo '<a href="'.esc_url( home_url('/')).'">';
                        echo wp_get_attachment_image($logo, 'full', false, array('alt' => esc_attr( get_bloginfo('name') . ' logo' )));
                    echo '</a>';
                    if( get_post_type() == 'accommodation'){
                        echo'<div class="accReviewWrapper">';
                            echo do_shortcode('[company_rating]');
                        echo'</div>'; #accReviewWrapper
                    }
                echo '</div>'; #logoWrap

                if($header_filter == true){
                    echo '<div class="resortFilterWrap">';
                      echo do_shortcode('[newResortFilters]');
                    echo '</div>'; # /resortFilterWrap
                }

                if ( ! empty($search) ){
                    echo '<div class="searchWrap">';
                        echo '<form method="get" id="searchform" action="'.esc_url( home_url('/')).'" role="search" aria-label="Site Search">';
                            echo '<div class="search-text">';
                                echo '<label for="search-input" class="screen-reader-text">Search resorts</label>';
                                echo '<input type="text" placeholder="Search..." value="'.esc_attr( get_search_query() ).'" name="s" id="search-input" autocomplete="off" />';
                                echo '<button type="submit" id="searchsubmit" aria-label="Submit Search">';
                                echo '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path></svg>';
                                echo '</button>';
                            echo '</div>';
                        echo '</form>';
                    echo '</div>'; #searchWrap
                }
            echo '</div>'; #container
        echo '</div>'; #topHeader
        echo '<div class="menuHeader">';
            echo '<div class="container">';
                echo '<div class="navWrap">';
                    if (has_nav_menu('main-menu')) {
                        wp_nav_menu(array(
                            'theme_location' => 'main-menu',
                            'menu_class' => 'mainMenu'
                        ));
                    }
                echo '</div>'; #navWrap
                echo '<div class="btnWrap" aria-label="Header Quick Links">';
                    if ( ! empty($buttons) && is_array($buttons) ) {
                        echo '<ul>';
                            foreach ( $buttons as $btn ) {

                                $headlink    = $btn['headlink'] ?? [];
                                $link_url    = $headlink['url'] ?? '#';
                                $link_title  = $headlink['title'] ?? '';
                                $link_target = $headlink['target'] ?? '_self';
                               
                                echo '<li>';
                                    echo '<a class="btn" href="'.esc_url($link_url).'" target="'.esc_attr($link_target).'">';
                                        echo esc_html($link_title);
                                    echo '</a>';
                                echo '</li>';
                            }
                        echo '</ul>';
                    }
                echo '</div>'; #btnWrap
            echo '</div>'; #container
        echo '</div>'; #topHeader
        get_template_part('mobile-header');

        if (!is_front_page()){
            echo '<div class="breadcrumb-wrapper full-section"
                role="Breadcrumb" 
                aria-label="Breadcrumb">';
                echo '<div class="container">'; 
                    if ( function_exists('yoast_breadcrumb') ) {
                        yoast_breadcrumb(
                            '<nav class="yoast-breadcrumbs" aria-label="Breadcrumbs">',
                            '</nav>'
                        );
                    }
                echo '</div>';
            echo '</div>';
        }

    echo '</header>';

?>

