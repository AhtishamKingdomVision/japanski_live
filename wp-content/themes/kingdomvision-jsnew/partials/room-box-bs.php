<?php

/**
 * Room Card Template for Booking System
 * Displays a single room with pricing, amenities, and booking options
 * 
 * Required $args keys:
 *   - property_id: Numeric property ID
 *   - room: Array of room data (RoomName, MaximumAdults, RoomImages, etc.)
 *   - rb: RoomBoss data array (optional)
 */

try {
    // ✅ STEP 1: Validate and extract arguments with defaults
    $args = is_array($args) ? $args : [];
    $property_id = intval($args['property_id'] ?? 0);
    $room = is_array($args['room'] ?? []) ? $args['room'] : [];
    $rb = is_array($args['rb'] ?? []) ? $args['rb'] : [];

    // Validate room data exists
    if (empty($room)) {
        return;
    }

    // ✅ STEP 2: Extract room identifiers with validation
    $room_id = ($room['ActualRoomId'] ?? 0);
    if (empty($room_id)) {
        return;
    }

    $room_post_id = get_post_id_by_typeId($room_id, 'room');
    if (empty($room_post_id)) {
        return;
    }

    $acc_id = 0;
    if (!empty($property_id)) {
        $acc_id = get_post_id_by_typeId($property_id, 'accommodation');
    }

    // ✅ STEP 3: Extract and sanitize room details
    $name = sanitize_text_field($room['RoomName'] ?? '');
    $guests = intval($room['MaximumAdults'] ?? 0);
    $bedrooms = intval($room['numberBedrooms'] ?? 0);
    $bathrooms = intval($room['numberBathrooms'] ?? 0);
    $sqm = sanitize_text_field($room['squareMeter'] ?? '');

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

    // ✅ STEP 4: Process and validate room image URL
    $image_url = '';
    if (!empty($room['RoomImages']) && is_array($room['RoomImages']) && !empty($room['RoomImages'][0])) {
        $imageData = $room['RoomImages'][0];
        
        // Check if path is already a complete URL
        if (!empty($imageData['path']) && filter_var($imageData['path'], FILTER_VALIDATE_URL)) {
            $image_url = esc_url_raw($imageData['path']);
        } elseif (!empty($imageData['path']) && !empty($imageData['file_name'])) {
            // Construct full URL from components
            $image_url = esc_url_raw(KV_BOOKING_SYSTEM_BASE . '/storage' . $imageData['path'] . $imageData['file_name']);
        }
    }

    // Fallback to placeholder if no image found
    if (empty($image_url)) {
        $image_url = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
    }

    // ✅ STEP 5: Determine unit type (RoomBoss or BedBank)
    // CTA follows explicit is_roomboss meta only so BedBank conversion shows
    // "Request Booking" even when leftover hotel/room ids still exist.
    $acc_id = get_post_id_by_typeId($property_id, 'accommodation');
    $bookingPermission = get_field('acc_booking_permission', $acc_id) ?: '';
    if (function_exists('kv_property_shows_roomboss_booking_cta')) {
        $is_roomboss = kv_property_shows_roomboss_booking_cta($acc_id, $property_id);
    } elseif (function_exists('kv_explicit_is_roomboss_meta')) {
        $is_roomboss = kv_explicit_is_roomboss_meta($acc_id) === true;
    } else {
        $is_roomboss_raw = !empty($acc_id) ? get_post_meta($acc_id, 'is_roomboss', true) : '';
        $is_roomboss = ($is_roomboss_raw === true || $is_roomboss_raw === 1 || $is_roomboss_raw === '1');
    }
    
    $unit_type = $is_roomboss ? 'roomboss' : 'bedbank';

} catch (Exception $e) {
    // ❌ Handle unexpected errors
    error_log('Error in room-box-bs template: ' . $e->getMessage());
    return;
}

    $resort_name = '';
    $categories = wp_get_post_terms($acc_id, 'accommodation-cat', ['parent' => 0]);
    if (!empty($categories) && !is_wp_error($categories)) {
        $resort_name = str_replace(' Accommodation', '', sanitize_text_field($categories[0]->name ?? ''));
    }
?>

<div class="room-card t1" data-bedroom="<?php echo esc_attr($bedrooms); ?>" actual_room_id="<?php echo esc_attr($room_id); ?>">
    <div class="room-img" style="position: relative;">
        <a href="<?php echo esc_url($image_url); ?>" data-fancybox="room-gallery-<?php echo $room_post_id; ?>" class="room-img-link">
            <div class="room-img"
                style="background-image: url('<?php echo esc_url($image_url); ?>');"
                aria-label="<?php echo esc_attr($name); ?>"
                role="img">
            </div>
        </a>
        <div class="detail_btn">
            <img src="<?php echo get_template_directory_uri() . '/images/search-icon.png'?>" class="search-detail-btn" alt="Search">
        </div>
    </div>
    <?php if (!empty($room_merged_gallary)) : ?>
        <div class="hidden-gallery" style="display:none;">
            <?php foreach ($room_merged_gallary as $gal_url) :
                $gal_url = esc_url((string)$gal_url);
                if (empty($gal_url) || $gal_url === esc_url($image_url)) continue; // Skip the main image if it's already used as the cover trigger
                ?><a href="<?php echo $gal_url; ?>" data-fancybox="room-gallery-<?php echo $room_post_id; ?>"></a><?php
            endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="rc_cover t1bs">
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
            <!-- Room Action Buttons -->
            <div class="room-btns">
                <?php 
                if ( get_post_meta( $acc_id, 'is_price_excluded', true ) === '1' ): ?>
                    <button bookingPermission="<?php echo esc_attr($bookingPermission); ?>" class="btn enq-btn bedbank_btn" hotel-name="<?php echo esc_attr(get_the_title($acc_id)); ?>" hotel-id="<?php echo esc_attr($property_id); ?>" room-title="<?php echo esc_attr(get_the_title($room_id)); ?>" resort-name="<?php echo esc_attr($resort_name); ?>">Enquire Now</button>
                <?php else : 
                    if ($is_roomboss) :
                        if (!empty($bookingPermission)) :
                            if (strpos($bookingPermission, 'REQUEST') !== false) : ?>                    
                                <!-- <button bookingPermission="<?php //echo $bookingPermission ?>" class="btn req-book book-btn roomboss_btn" hotel-id="<?php //echo esc_attr($property_id); ?>">Request to Book</button> -->
                                <button bookingPermission="<?php echo $bookingPermission ?>" class="btn book-btn bs_btn roomboss_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Book Now</button>
                            <?php elseif (strpos($bookingPermission, 'RESERVATION') == false) : ?>
                                <button bookingPermission="<?php echo $bookingPermission ?>" class="btn book-btn bs_btn roomboss_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Book Now</button>
                            <?php endif; ?>
                        <?php else : ?>
                            <button bookingPermission="<?php echo $bookingPermission ?>" class="btn book-btn bs_btn roomboss_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Book Now</button>
                        <?php endif; ?>
                    <?php else : ?>
                            <button bookingPermission="<?php echo $bookingPermission ?>" class="btn chk-avl-btn book-btn bs_btn bedbank_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Request Booking</button>
                        <?php endif; ?>
                <?php endif; ?>
                <a href="javascript:;" class="btn details-btn" property-id="<?php echo esc_attr($property_id); ?>" room-id="<?php echo esc_attr($room_id); ?>"> Details </a>
            </div>
        </div>
    </div>
</div>