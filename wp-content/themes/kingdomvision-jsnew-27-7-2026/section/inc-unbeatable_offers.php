<?php 

// Step 1: Query accommodations with meta_key = 1
$args = [
    'post_type'      => 'accommodation',
    'posts_per_page' => 10,
    'orderby'        => 'rand',
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => 'is_discount', // <-- replace this
            'value'   => '1',
            'compare' => '='
        ]
    ],
];

$query = new WP_Query($args);

if ($query->have_posts()) :

    echo '<section ' . SectionAttributes($section, 'full-section unbeatable_offers') . ' ' . BackgroundFromSection($section) . ' aria-labelledby="unbeatable-offers-title">';
        echo '<div class="container">';
            echo TitleFromSection($section);
        echo '</div>';

        echo '<div class="offer_cover">';
            echo '<div class="offers" role="list">';

            while ($query->have_posts()) : $query->the_post();

                $post_id = get_the_ID();

                // Get data (adjust based on your ACF/meta structure)
                $image   = has_post_thumbnail( $post_id ) ? get_post_thumbnail_id($post_id) : 0;
                $title = get_the_title($post_id);
                $button_url = get_permalink($post_id);

                $display_categories = hz_get_allowed_accommodation_areas($post_id);

                $areas_display = implode(', ', $display_categories);
                $short_desc = get_field('bs_short_description', $post_id) ? trim( sanitize_text_field( get_field('bs_short_description', $post_id) ) ) : '';

                echo '<div class="off_ers" role="listitem">';

                    if ($image && $image > 0 ) {
                        echo wp_get_attachment_image(
                            $image,
                            'full',
                            false,
                            [
                                'class' => 'offer-image',
                                'alt'   => esc_attr(get_post_meta($image, '_wp_attachment_image_alt', true))
                            ]
                        );
                    }

                    else{
                        echo '<img src="'.get_template_directory_uri().'/images/placeholder-featured.jpg'.'" alt="'.$title.'">';
                    }

                    echo '<div class="offer-content">';
                        echo wp_kses_post($title);
                        // Area and Resort Info
                        if (!empty($areas_display)) : ?>
                            <p class="area_resort">
                                <?php echo esc_html($areas_display); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Short Description -->
                        <?php if (!empty($short_desc)) : ?>
                            <p class="desc"><?php echo wp_trim_words(esc_html($short_desc), 15); ?></p>
                        <?php endif;

                        echo '<a class="btn" href="' . esc_url($button_url) . '" target="_self">';
                            echo 'Learn more';
                        echo '</a>';

                    echo '</div>';

                echo '</div>';

            endwhile;

            echo '</div>';
        echo '</div>';

    echo '</section>';

    wp_reset_postdata();

endif;
?>