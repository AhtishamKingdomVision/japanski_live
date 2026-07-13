<?php

/**
 * Accommodation Card Template (ACF-based with RoomBoss fallback)
 * Displays a single accommodation listing with image, price, and booking options
 * 
 * Uses ACF fields for property data and RoomBoss API for live pricing
 * Requires: get_the_ID(), get_field(), get_the_terms(), get_hotel_rooms()
 */

try {
    // ✅ STEP 1: Validate post context
    $post_id = get_the_ID();
    if (empty($post_id)) {
        return;
    }

    $args = is_array($args) ? $args : [];
    $top_label = $args['top_label'] ?? '';
    // Ensure ACF is active
    if (!function_exists('get_field')) {
        return;
    }

    // ✅ STEP 2: Retrieve and validate ACF fields
    $is_roomboss = (bool) get_field('is_roomboss', $post_id);
    $image = has_post_thumbnail($post_id) ? get_post_thumbnail_id($post_id) : get_template_directory_uri().'/images/placeholder-featured.jpg';
    $short_desc = get_field('bs_short_description', $post_id) ? trim( sanitize_text_field( get_field('bs_short_description', $post_id) ) ) : '';
    $db_price = (float) get_field('min_room_price', $post_id) ? get_field('min_room_price', $post_id) : '';
    $overlay = (bool) get_field('overlay', $post_id) ? get_field('overlay', $post_id) : false;
    $hotel_tid = (int) get_field('acc_hotel_id', $post_id) ? get_field('acc_hotel_id', $post_id) : '';

    // ✅ STEP 3: Process resort and area information
    $display_categories = hz_get_allowed_accommodation_areas($post_id);

    // ✅ STEP 5: Calculate pricing with fallback to RoomBoss
    $rb_price = 0;
    $rb = $GLOBALS['kv_roomboss_current'] ?? null;
    $bookingPermission = get_field('acc_booking_permission', $post_id);
    $rb_price = 0;

    if( isset( $rb ) ){
            if (!empty($rb) && isset($rb['min_price'])) {

                $rb_price = (float) $rb['min_price'];
            }
        
            if ( $rb['bookingPermission'] ) {
                $bookingPermission = $rb['bookingPermission'];
            }

    }
    $price = ($db_price > 0) ? $db_price : $rb_price;

    $areas_display = implode(', ', $display_categories);

    $image_url = '';
    if (!empty($image)) {
        if (is_numeric($image)) {
            // Image is attachment ID
            $img = wp_get_attachment_image_src((int) $image, 'large');
            if (!empty($img[0])) {
                $image_url = esc_url_raw($img[0]);
            }
        } elseif (is_array($image)) {
            // Image is array (ACF image field)
            if (!empty($image['sizes']['large'])) {
                $image_url = esc_url_raw($image['sizes']['large']);
            } elseif (!empty($image['url'])) {
                $image_url = esc_url_raw($image['url']);
            }
        } elseif (is_string($image)) {
            // Image is string URL
            $image_url = esc_url_raw($image);
        }
    }

    // ✅ STEP 6: Process accommodation image
    // Fallback to placeholder if no image found
    if (empty($image_url)) {
        $image_url = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
    }

    // ✅ STEP 7: Get room count safely
    $room_count = 0;
    if (!empty($hotel_tid) && function_exists('get_hotel_rooms')) {
        $hotel_data = get_hotel_rooms($hotel_tid);
        if (is_array($hotel_data) && isset($hotel_data['rooms'])) {
            $room_count = count($hotel_data['rooms']);
        }
    }

    // ✅ STEP 8: Prepare button state
    $post_title = get_the_title($post_id);
    $post_permalink = get_permalink($post_id);

    // Determine trim length for short_desc based on post_title length.
    // This is a server-side heuristic as actual line wrapping depends on client-side rendering.
    $short_desc_trim_length = (strlen($post_title) > 25) ? 10 : 15;

} catch (Throwable $e) {
    // ❌ Handle unexpected errors (Exceptions and PHP 7+ Errors)
    error_log('Error in accommodation-box template: ' . $e->getMessage());
    return;
}

if (!empty($post_title)) : ?>
<div class="result-card accom-card <?php echo $overlay ? 'has-overlay' : ''; ?>" data-hotel-id="<?php echo esc_attr($hotel_tid); ?>">
    <?php 
    if ( $top_label && !empty( $top_label ) ) : ?>
        <div class="top_label"><?php echo esc_html($top_label); ?></div>
    <?php endif; ?>
    <a href="<?php echo esc_url($post_permalink); ?>">
        <div class="accom-image" style="background-image: url('<?php echo esc_url($image_url); ?>');"
            aria-label="<?php echo esc_attr($post_title); ?>"
            role="img" title="<?php echo esc_attr($post_title); ?>"></div>

        <div class="accom-content">
            <h3><?php echo esc_html($post_title); ?></h3>

            <!-- Price Display -->
            <?php if ( $price > 0 && get_post_meta( $post_id, 'is_price_excluded', true ) !== '1' ) : 
                
                $checkin_str = isset( $_POST['checkin'] ) ? $_POST['checkin'] : '';
                $checkout_str = isset( $_POST['checkout'] ) ? $_POST['checkout'] : '';
                $nights = 0;
                $guests = 0;

                if( !empty( $checkin_str ) && !empty( $checkout_str ) ){

                    // Create DateTime objects explicitly using day/month/year format
                    $checkin = DateTime::createFromFormat('d/m/Y', $checkin_str);
                    $checkout = DateTime::createFromFormat('d/m/Y', $checkout_str);
    
                    // Calculate the difference
                    $interval = $checkin->diff($checkout);
    
                    // Output the duration in total days
                    $nights = isset ($interval->days ) ? $interval->days : 0;
                    
                    $guests = isset( $_POST['guests'] ) ? $_POST['guests'] : 0;
                }
                ?>
                <p class="price" excl="<?php echo get_post_meta( $post_id, 'is_price_excluded', true ) === '1' ? '1' : '0'; ?>">
                    JPY <?php echo number_format_i18n((float) $price); if( $nights > 0 ):?>
                    <span class="num_nights">&nbsp; &nbsp;<?php echo $nights; ?> Nights</span> <?php endif; ?> &middot; <span class="num_guests"><?php echo $guests; ?> Guests </span>
                </p>
            <?php endif; ?>

            <!-- Area and Resort Info -->
            <?php if (!empty($areas_display)) : ?>
                <p class="area_resort">
                    <?php echo esc_html($areas_display); ?>
                </p>
            <?php endif; ?>

            <!-- Short Description -->
            <?php if (!empty($short_desc)) : ?>
                <p class="desc"><?php echo wp_trim_words(esc_html($short_desc), $short_desc_trim_length); ?></p>
            <?php endif; ?>
            
            <!-- Action Button -->
            <div class="acc_btns">

                <?php

                // if ( get_post_meta( $post_id, 'is_price_excluded', true ) === '1' ): ?>

                    <span class="enquire_btn btn" data-room_id="<?php echo esc_attr($post_id); ?>"> Enquire Now </span>

                <?php //else : 

                    // if ($is_roomboss) : 

                    //     if ($bookingPermission && $bookingPermission !== null ) : 

                    //         if( strpos($bookingPermission, 'REQUEST') !== false ) : ?>

                                <!-- <span class="book_btn btn" data-room_id="<?php //echo esc_attr($post_id); ?>"> Book Now </span> -->

                            <?php //elseif( strpos($bookingPermission, 'RESERVATION') !== false ) : ?>

                                <!-- <span class="book_btn btn" data-room_id="<?php //echo esc_attr($post_id); ?>">

                                    Book Now

                                </span> -->

                            <?php //endif;

                            // else : 

                            ?>

                            <!-- <span class="book_btn btn" data-room_id="<?php //echo esc_attr($post_id); ?>"> Book Now </span> -->

                        <?php //endif; ?>

                    <?php //else : ?>

                        <!-- <span class="enquire_btn btn" data-room_id="<?php //echo esc_attr($post_id); ?>"> Request Booking </span> -->

                    <?php //endif; ?>

                <?php //endif; ?>

                <!-- View Link -->

                <span class="view_book btn">

                    View

                </span>

            </div>
        </div>
    </a>

</div>
<?php endif; ?>