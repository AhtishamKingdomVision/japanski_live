<?php

/* cta with bg_image */
$content_area   = get_field( 'bg_cta_content', 'option' ) ?? '';
$bplayout       = get_field( 'bg_cta_bullet_point_layout', 'option' )  ?? '';
$bullet         = get_field( 'bg_cta_bullet_points', 'option' )  ?? [];
$bg_icon        = get_field( 'bg_cta_bg_icon', 'option' )  ?? '';
$button         = get_field( 'bg_cta_button', 'option' )  ?? [];
$ovcolor        = get_field( 'bg_cta_overlay_color', 'option' );

$bg_options      = get_field( 'bg_cta_bg_options', 'option' ) ? get_field( 'bg_cta_bg_options', 'option' ) : '';
$bg_image        = get_field( 'bg_cta_bg_image', 'option' ) ? get_field( 'bg_cta_bg_image', 'option' ) : '';
$bg_color        = get_field( 'bg_cta_bg_color', 'option' ) ? get_field( 'bg_cta_bg_color', 'option' ) : '';
$padding_top     = get_field( 'bg_cta_padding_top', 'option' ) ? get_field( 'bg_cta_padding_top', 'option' ) : '';
$padding_bottom  = get_field( 'bg_cta_padding_bottom', 'option' ) ? get_field( 'bg_cta_padding_bottom', 'option' ) : '';

$title          = get_field( 'bg_cta_title', 'option' ) ?? '';
$font_size      = get_field( 'bg_cta_font_size', 'option' ) ?? '';
$title_tag      = get_field( 'bg_cta_title_tag', 'option' ) ?? 'h2';
$title_color    = get_field( 'bg_cta_title_color', 'option' ) ?? '';
$title_align    = get_field( 'bg_cta_title_align', 'option' ) ?? 'left';

$cta_section = ['bg_options' => $bg_options, 'bg_image' => $bg_image, 'bg_color' => $bg_color, 'padding_top' => $padding_top, 'padding_bottom' => $padding_bottom, 'title' => $title, 'font_size' => $font_size, 'title_tag' => $title_tag, 'title_color' => $title_color, 'title_align' => $title_align];

/* === Section ID & Extra Class === */
$cta_section_id = !empty(get_field('bg_cta_section_id', 'option'))
    ? ' id="' . esc_attr(get_field('bg_cta_section_id', 'option')) . '"'
    : '';

$extra_class = !empty(get_field('bg_cta_section_class', 'option'))
    ? ' ' . esc_attr(get_field('bg_cta_section_class', 'option'))
    : '';

$bg_cta = !empty(get_field('bg_cta_button', 'option'))
    ? get_field('bg_cta_button', 'option')
    : '';
$cta_target = !empty($bg_cta) && !empty($bg_cta['target'])
    ? $bg_cta['target']
    : '';
$cta_url = !empty($bg_cta) && !empty($bg_cta['url'])
    ? $bg_cta['url']
    : '';
$cta_text = !empty($bg_cta) && !empty($bg_cta['title'])
    ? $bg_cta['title']
    : '';

/* === Start Section === */
echo '<section class="full-section cta_with_bg_image ' . esc_attr($bg_icon) . $extra_class . '" ' . $cta_section_id . ' ' . BackgroundFromSection($cta_section) . ' role="region" aria-labelledby="cta-title">';
    echo '<div class="section-overlay" style="background: linear-gradient(0deg, rgba(0, 17, 31, 0.' . $ovcolor . ') 0%, rgba(0, 17, 31, 0.' . $ovcolor . ') 100%);"></div>';
    echo '<div class="container">';
        echo '<div class="cwbg_cover">';

            // Accessible Title
            echo TitleFromSection($cta_section);

            // Content
            if (!empty($content_area)) {
                printf('<div class="content_area">%s</div>', wp_kses_post($content_area));
            }

            // Bullet List
            if (!empty($bullet) && is_array($bullet)) {
                echo '<ul class="cta-list ' . esc_attr($bplayout) . '" role="list">';
                    foreach ($bullet as $value) {
                        $points = $value['points'] ?? '';
                        if (!empty($points)) {
                            echo '<li>' . esc_html($points) . '</li>';
                        }
                    }
                echo '</ul>';
            }

            // Button
            if (!empty($button)) :
                $link_url    = $button['url'] ?? '';
                $link_title  = $button['title'] ?? '';
                $link_target = !empty($button['target']) ? $button['target'] : '_self';

                if ($link_url && $link_title) {
                    echo '<a class="btn" href="' . esc_url($link_url) . '" target="' . esc_attr($link_target) . '">'
                        . esc_html($link_title) .
                    '</a>';
                }
            endif;

        echo '</div>'; // .cwbg_cover
    echo '</div>'; // .container
echo '</section>';
/* cta with bg_image */

$content = get_field( 'client_quote_desc' ) ? get_field( 'client_quote_desc' ) : get_field( 'quote_desc' );

$parent_cat = str_replace(' Accommodation', '', hz_get_parent_category( get_the_ID() ));
// $ammenites_title = title . post parent categoy . FACILITIES;
// Return nothing if no title
$cat_name = str_replace(' Accommodation', '', hz_get_parent_category( get_the_ID() ));
$ammenites_title = get_the_title();
    if ( ! empty( $cat_name ) && strpos( strtolower( $ammenites_title ), strtolower( $cat_name ) ) !== false ) {
    $ammenites_title .= ' Facilities';
} elseif ( ! empty( $cat_name ) ) {
    $ammenites_title .= ' ' . $cat_name . ' Facilities';
} else {
    $ammenites_title .= ' Facilities';
}

$ammenites = get_the_terms(get_the_ID(), 'property_ammenites');
// echo '<div class="full-section room-expert-rec">';
//     echo '<div class="container">';
//         echo kv_get_expert_recommendation_cta();
//     echo '</div>';
// echo '</div>';
echo '<section class="full-section about-accomodation" ' . BackgroundFromSection($section) . '>';
    echo '<div class="container">';
        echo '<div class="flex-wrap">';
            echo '<div class="left-side">';
                echo HZoverviewTitleFromSection($section);
                echo WysiwygReadMoreLess($content);
            echo '</div>'; #left-side
            echo '<div class="right-side">';
                if($ammenites_title){
                    echo '<h3>'.$ammenites_title.'</h3>';
                }
                if($ammenites && !is_wp_error($ammenites)){
                    $count = count($ammenites);
                    echo '<ul class="ammenites" data-ammenites="'.$count.'">';
                        foreach ($ammenites as $term) {
                            $icon = get_field('acc_facility_icon', 'property_ammenites_' . $term->term_id);
                            $label = $term->name;

                            if ($label && !empty($icon) && strtolower($icon) !== 'none') {
                                echo '<li>';
                                    echo '<span class="icon">';
                                        echo $icon;
                                    echo '</span>';
                                    echo '<span class="text">' . esc_html($label) . '</span>';
                                echo '</li>';
                            }
                        }
                    echo '</ul>';
                }
            echo '</div>'; #right-side
        echo '</div>'; #flex-wrap
    echo '</div>'; // container
echo '</section>'; #about-accomodation
// echo '<div class="full-section room-strong-rec">';
//     echo '<div class="container">';
//         echo kv_get_stronger_recommendation_cta();
//     echo '</div>'; //container
// echo '</div>'; // room-strong-rec

$is_roomboss = get_field('is_roomboss');

$is_price_exc = get_field('is_price_excluded');
if( !$is_roomboss || $is_price_exc ){
    echo '<section class="full-section enquiry_form">';
        echo '<div class="container">';
            echo get_enquiry_form_html();
        echo '</div>'; //container
    echo '</section>'; // room-boss
}
?>