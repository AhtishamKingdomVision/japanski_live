<?php

/* cta with bg_image */
    $content_area   = get_field( 'bg_cta_content', 'option' ) ?? '';
    $bplayout       = get_field( 'bg_cta_bullet_point_layout', 'option' )  ?? '';
    $bullet         = get_field( 'bg_cta_bullet_points', 'option' )  ?? [];
    $bg_icon        = get_field( 'bg_cta_bg_icon', 'option' )  ?? '';
    $button         = get_field( 'bg_cta_button', 'option' )  ?? [];
    $ovcolor        = get_field( 'bg_cta_overlay_color', 'option' ) ;

    $bg_options      = get_field( 'bg_cta_bg_options', 'option' ) ? get_field( 'bg_cta_bg_options', 'option' ): '';
    $bg_image        = get_field( 'bg_cta_bg_image', 'option' ) ? get_field( 'bg_cta_bg_image', 'option' ): '';
    $bg_color        = get_field( 'bg_cta_bg_color', 'option' ) ? get_field( 'bg_cta_bg_color', 'option' ): '';
    $padding_top     = get_field( 'bg_cta_padding_top', 'option' ) ? get_field( 'bg_cta_padding_top', 'option' ): '';
    $padding_bottom  = get_field( 'bg_cta_padding_bottom', 'option' ) ? get_field( 'bg_cta_padding_bottom', 'option' ): '';

    $title          = get_field( 'bg_cta_title' , 'option' ) ?? '';
    $font_size      = get_field( 'bg_cta_font_size' , 'option' ) ?? '';
    $title_tag      = get_field( 'bg_cta_title_tag' , 'option' ) ?? 'h2';
    $title_color    = get_field( 'bg_cta_title_color' , 'option' ) ?? '';
    $title_align    = get_field( 'bg_cta_title_align' , 'option' ) ?? 'left';
    
    $cta_section = ['bg_options' => $bg_options, 'bg_image' => $bg_image, 'bg_color' => $bg_color, 'padding_top' => $padding_top, 'padding_bottom' => $padding_bottom, 'title' => $title, 'font_size' => $font_size, 'title_tag' => $title_tag, 'title_color' => $title_color, 'title_align' => $title_align];

    /* === Section ID & Extra Class === */
    $cta_section_id  = !empty(get_field('bg_cta_section_id', 'option' ) ) 
        ? ' id="' . esc_attr(get_field('bg_cta_section_id', 'option' ) ) . '"' 
        : '';

    $extra_class = !empty(get_field('bg_cta_section_class', 'option' ) ) 
        ? ' ' . esc_attr(get_field('bg_cta_section_class', 'option' ) ) 
        : '';

    $bg_cta = !empty(get_field('bg_cta_button', 'option' ) )
        ? get_field('bg_cta_button', 'option' )
        : '';
    $cta_target = !empty( $bg_cta ) && !empty($bg_cta['target'])
        ? $bg_cta['target']
        : '';
    $cta_url = !empty( $bg_cta ) && !empty($bg_cta['url'])
        ? $bg_cta['url']
        : '';
    $cta_text = !empty( $bg_cta ) && !empty($bg_cta['title'])
        ? $bg_cta['title']
        : '';

    /* === Start Section === */
    echo '<section class="full-section cta_with_bg_image ' . esc_attr( $bg_icon ) . $extra_class . '" ' . $cta_section_id . ' ' . BackgroundFromSection($cta_section) . ' role="region" aria-labelledby="cta-title">';
        echo '<div class="section-overlay" style="background: linear-gradient(0deg, rgba(0, 17, 31, 0.'. $ovcolor .') 0%, rgba(0, 17, 31, 0.'. $ovcolor .') 100%);"></div>';
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

    $property_post_id = get_the_ID();
    $property_api_id = get_field('property_id', $property_post_id);
    $accordion = []; // This will hold our merged FAQs.

    if ($property_api_id) {
        // Define API URL and authentication token
        $api_url = 'https://stay.japanskiexperience.com/api/wp-property-faqs';
        $auth_token = '12516|YStf8rlvuxaIoyFQf5ILa3Z0VKNtGd7dHVwkjG0P562fba70'; // As provided in the curl command

        // Prepare the request body
        $body = [
            'propertyIds' => [(int)$property_api_id],
        ];

        // Prepare the arguments for wp_remote_post
        $args = [
            'method'    => 'POST',
            'timeout'   => 30,
            'headers'   => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $auth_token,
            ],
            'body'      => json_encode($body),
        ];

        // Make the API call
        $response = wp_remote_post($api_url, $args);

        // Check for errors and process the response
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);

            // pre( 'faq data' );
            // pre( $data, 1 );

        // 1. Add default FAQs first
        if (!empty($data['default_faqs']) && is_array($data['default_faqs'])) {
            foreach ($data['default_faqs'] as $faq) {
                $accordion[] = [
                    'title'       => $faq['title'] ?? '',
                    'description' => $faq['description'] ?? '',
                ];
            }
        }

        // 2. Add property-specific FAQs if they exist
        if (!empty($data['property']) && is_array($data['property'])) {
            foreach ($data['property'] as $_faqs) {
                $faqs = $_faqs['faqs'];
                foreach ($faqs as $faq) {
                    $accordion[] = [
                        'title'       => $faq['title'] ?? '',
                        'description' => $faq['description'] ?? '',
                    ];
                }

            }
        }
    }
}
echo '<section class="full-section accordion" ' . BackgroundFromSection($section) . '>';
    echo '<div class="container">';
        echo TitleFromSection($section);
        // A11Y: accordion wrapper with role + label
        echo '<div class="acco" role="tablist">';
        if (!empty($accordion)) {
            foreach ($accordion as $index => $item) {
                $title = $item['title'] ?? '';
                $description = $item['description'] ?? '';
                // Safe, unique IDs for A11Y
                $tab_id  = 'acc-tab-' . $index;
                $panel_id = 'acc-panel-' . $index;
                echo '<div class="accor_inn">';
                    // A11Y button for title
                    echo '<h3 
                            class="accor_trigger" 
                            id="' . esc_attr($tab_id) . '" 
                            aria-expanded="false" 
                            aria-controls="' . esc_attr($panel_id) . '" 
                            role="tab"
                        >
                            ' . esc_html($title) . '
                        </h3>';
                    // Accordion content panel
                    echo '<div 
                            class="accor_content" 
                            id="' . esc_attr($panel_id) . '" 
                            role="tabpanel" 
                            aria-labelledby="' . esc_attr($tab_id) . '" 
                            hidden
                        >';
                        echo wp_kses_post($description);
                    echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>'; // acco
    echo '</div>'; // container
echo '</section>';
?>