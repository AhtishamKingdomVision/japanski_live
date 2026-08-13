<?php

/**
 * Room Card Template (ACF-based)
 * Displays a single room with amenities and booking/inquiry options
 * 
 * Required $args keys:
 *   - room: WP_Post object for the room post
 *   - rb: RoomBoss API data (optional)
 *   - property_id: Numeric property/accommodation ID
 */

try {
    // ✅ STEP 1: Validate and extract arguments
    $args = is_array($args) ? $args : [];
    $room = $args['room'] ?? null;
    $property_id = intval($args['property_id'] ?? 0);

    // Validate room object exists
    if (empty($room) || !is_object($room)) {
        return;
    }

    $room_id = ($room->ID ?? 0);
    $actual_room_id = get_field('actual_room_id', $room_id);
    if (empty($room_id)) {
        return;
    }

    // ✅ STEP 2: Get accommodation post ID for related data
    $acc_id = !empty($args['acc_id']) ? (int) $args['acc_id'] : 0;
    if ($acc_id <= 0 && !empty($property_id)) {
        $acc_id = (int) get_post_id_by_typeId($property_id, 'accommodation');
    }
    if ($acc_id <= 0 && is_singular('accommodation')) {
        $acc_id = (int) get_the_ID();
    }

    // ✅ STEP 3: Extract and sanitize room details from ACF
    $name = get_the_title($room_id);
    $guests = intval(get_field('room_guests', $room_id) ?? 0);
    $bedrooms = intval(get_field('room_bedroom', $room_id) ?? 0);
    $bathrooms = intval(get_field('room_bathroom', $room_id) ?? 0);
    $sqm = sanitize_text_field(get_field('room_sqm', $room_id) ?? '');

    $room_image_gallery = kv_get_meta_images_gallery($room_id, 'room_pending_images');
    $room_ext_gallary = get_field('room_gallary', $room_id);
    // @$_GET['gall'] == 'yes' ? pre($room_image_gallery,1) : '';
    $room_merged_gallary = [];

    if( ( isset( $room_ext_gallary ) && is_array( $room_ext_gallary ) ) ){
        $room_merged_gallary = array_merge((array)$room_image_gallery, (array)$room_ext_gallary);
    }
    else{
        $room_merged_gallary = !empty( $room_image_gallery ) ? $room_image_gallery : [];
    }

    // ✅ STEP 4: Process room image with fallback
    $image_url = (string) get_the_post_thumbnail_url($room_id, 'full');
    if (empty($image_url)) {
        $image_url = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
    }
    $image_url = esc_url_raw($image_url);

    // ✅ STEP 5: Get accommodation details (property type and location)
    // BedBank property → always "Request Booking" (ignore leftover room RoomBoss ids).
    // RoomBoss/hybrid → "Book Now" only when property CTA helper says so.
    // Passed rb=false (BedBank list / fallback) always wins.
    $is_roomboss = false;
    $area_list = [];
    $resort_name = '';
    $bookingPermission = '';

    $rb_arg = $args['rb'] ?? null;
    $force_bedbank_cta = ($rb_arg === false || $rb_arg === 0 || $rb_arg === '0');

    if (!empty($acc_id)) {
        if ($force_bedbank_cta) {
            $is_roomboss = false;
        } elseif (function_exists('kv_property_shows_roomboss_booking_cta')) {
            $is_roomboss = kv_property_shows_roomboss_booking_cta($acc_id, $property_id);
        } elseif (function_exists('kv_explicit_is_roomboss_meta')) {
            $is_roomboss = kv_explicit_is_roomboss_meta($acc_id) === true;
        } else {
            $is_roomboss_raw = get_post_meta($acc_id, 'is_roomboss', true);
            $is_roomboss = ($is_roomboss_raw === true || $is_roomboss_raw === 1 || $is_roomboss_raw === '1');
        }
        $bookingPermission = get_field('acc_booking_permission', $acc_id);

        // Get area information
        $area_field = get_field('area', $acc_id);
        if (!empty($area_field) && is_array($area_field)) {
            $area_list = array_filter(array_map('sanitize_text_field', $area_field));
        }

        // Get parent term
        $categories = wp_get_post_terms($acc_id, 'accommodation-cat', ['parent' => 0]);
        if (!empty($categories) && !is_wp_error($categories)) {
            $resort_name = str_replace(' Accommodation', '', sanitize_text_field($categories[0]->name ?? ''));
        }
    } elseif ($force_bedbank_cta) {
        $is_roomboss = false;
    }

    // ✅ STEP 6: Build location display string
    $location_display = '';
    if (!empty($area_list)) {
        $location_display = implode(', ', $area_list);
    }
    if (!empty($resort_name)) {
        $location_display = !empty($location_display) ? $location_display . ', ' . $resort_name : $resort_name;
    }

} catch (Exception $e) {
    // ❌ Handle unexpected errors
    error_log('Error in room-box template: ' . $e->getMessage());
    return;
}

?>

<div class="room-card t2" data-bedroom="<?php echo esc_attr($bedrooms); ?>" actual_room_id="<?php echo esc_attr($room_id); ?>">
    <a href="<?php echo esc_url($image_url); ?>" data-fancybox="room-gallery-<?php echo $room_id; ?>" class="room-img-link">
        <div class="room-img"
            style="background-image: url('<?php echo esc_url($image_url); ?>');"
            aria-label="<?php echo esc_attr($name); ?>"
            role="img">
            <div class="detail_btn">
                <img src="<?php echo get_template_directory_uri() . '/images/search-icon.png'?>" alt="Search">
            </div>
        </div>
    </a>

    <?php if (!empty($room_merged_gallary)) : ?>
        <div class="hidden-gallery" style="display:none;">
            <?php foreach ($room_merged_gallary as $gal_url) : 
                $gal_url = esc_url((string)$gal_url);
                // Skip the first image if it's already used as the cover trigger
                if (empty($gal_url) || $gal_url === esc_url($image_url)) continue; ?>
                <a href="<?php echo $gal_url; ?>" data-fancybox="room-gallery-<?php echo $room_id; ?>"></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="rc_cover t1box">
        <div class="room_details">
            <!-- Room Title -->
            <div class="room-title">
                <h3><?php echo esc_html($name); ?></h3>
            </div>
            <!-- Location Info -->
            <?php if (!empty($location_display)) : ?>
                <span><?php echo esc_html($location_display); ?></span>
            <?php endif; ?>
            <!-- Room Amenities -->
            <div class="room-info">
                <?php if ($guests > 0) : ?>
                    <span class="guest">Max Guests: <?php echo esc_html($guests); ?></span>
                <?php endif; ?>
    
                <?php if ($bedrooms > 0) : ?>
                    <span class="bad"><?php echo esc_html($bedrooms); ?> Bedrooms</span>
                <?php endif; ?>
    
                <?php if ($bathrooms > 0) : ?>
                    <span class="bath"><?php echo esc_html($bathrooms); ?> Bathrooms</span>
                <?php endif; ?>
    
                <?php if (!empty($sqm)) : ?>
                    <span class="sqm"><?php echo esc_html($sqm); ?> sqm</span>
                <?php endif; ?>
            </div>
            <!-- Action Buttons -->
            <div class="room-btns">
                <?php
                // Exclude prices / force_enquire → Enquire Now popup (no Request Booking).
                $force_enquire = !empty($args['force_enquire']);
                $is_price_excluded = $force_enquire
                    || (function_exists('kv_is_price_excluded') ? kv_is_price_excluded($acc_id) : (get_post_meta($acc_id, 'is_price_excluded', true) === '1'));

                if ($is_price_excluded) : ?>
                    <button bookingPermission="<?php echo $bookingPermission ?>" class="btn bedbank_btn enq-btn-popup" hotel-name="<?php echo esc_attr(get_the_title($acc_id)); ?>" hotel-id="<?php echo esc_attr($property_id); ?>" room-title="<?php echo esc_attr(get_the_title($room_id)); ?>" resort-name="<?php echo esc_attr($resort_name); ?>" >Enquire Now</button>
                <?php elseif ($is_roomboss) :
                        if (!empty($bookingPermission)) :
                            if (strpos($bookingPermission, 'REQUEST') !== false) : ?>                    
                                <button bookingPermission="<?php echo $bookingPermission ?>" class="btn book-btn roomboss_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Book Now</button>
                            <?php elseif (strpos($bookingPermission, 'RESERVATION') !== false) : ?>
                                <button bookingPermission="<?php echo $bookingPermission ?>" class="btn book-btn roomboss_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Book Now</button>
                            <?php endif; ?>
                        <?php else : ?>
                            <button bookingPermission="<?php echo $bookingPermission ?>" class="btn book-btn roomboss_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Book Now</button>
                        <?php endif; ?>
                <?php else : ?>
                            <button bookingPermission="<?php echo $bookingPermission ?>" class="btn chk-avl-btn book-btn bedbank_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Request Booking</button>
                <?php endif; ?>
                <a href="javascript:;" class="btn details-btn" property-id="<?php echo esc_attr($property_id); ?>" room-id="<?php echo esc_attr($room_id); ?>"> Details </a>
            </div>
        </div>
    </div>
</div>