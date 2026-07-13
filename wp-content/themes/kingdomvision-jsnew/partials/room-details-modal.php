<?php

/**
 * Room Details Modal Template
 * Displays detailed room information including gallery, amenities, and features
 * Used in modal popups for room details pages
 * 
 * Required $args keys:
 *   - room_id: Numeric room post ID
 *   - hotel_id: Numeric hotel/property ID
 */

try {
    // ✅ STEP 1: Validate and extract arguments
    $args = is_array($args) ? $args : [];
    $room_id = ($args['room_id'] ?? 0);
    $property_id = ($args['property_id'] ?? 0);
    $page_type = @$_POST['page'] ?? '';

    // Validate required parameters
    if (empty($room_id)) {
        return;
    }

    $room_id_by_roomTypeId = get_post_id_by_typeId($room_id, 'room');
    if( !empty($room_id_by_roomTypeId) ) {
        $room_id = $room_id_by_roomTypeId;
        // die('Found room ID by roomTypeId: ' . $room_id);
    }

    // ✅ STEP 2: Get and validate room post
    $post = get_post($room_id);
    if (empty($post) || !is_object($post)) {
        return;
    }

    $room_title = get_the_title($room_id);

    // ✅ STEP 3: Extract and validate ACF room details
    $guests = intval(get_field('room_guests', $room_id) ?? 0);
    $bedrooms = intval(get_field('room_bedroom', $room_id) ?? 0);
    $bathrooms = intval(get_field('room_bathroom', $room_id) ?? 0);
    $size = sanitize_text_field(get_field('room_size', $room_id) ?? '');
    $features = get_field('room_features', $room_id);
    $room_desc = get_field( 'client_description', $room_id );
    $bedding_options = get_the_terms( $room_id, 'bedding_options' );
    $occupency_options = get_the_terms( $room_id, 'room_occupencies' );

    // ✅ STEP 4: Get accommodation details for property type
    $acc_id = 0;
    if (!empty($property_id)) {
        $acc_id = get_post_id_by_typeId($property_id, 'accommodation');
    }

    $is_roomboss = false;
    $bookingPermission = '';
    if (!empty($acc_id)) {
        $is_roomboss = (bool) get_field('is_roomboss', $acc_id);
        $bookingPermission = get_field('acc_booking_permission', $acc_id);
    }

    // ✅ STEP 5: Get and validate room gallery
    $gallery = kv_get_meta_images_gallery( $room_id, 'room_pending_images');
    $gallery = (!empty($gallery) && is_array($gallery)) ? $gallery : [];

    // ✅ STEP 6: Validate features array
    if (!empty($features) && !is_array($features)) {
        $features = [];
    }

} catch (Exception $e) {
    // ❌ Handle unexpected errors
    error_log('Error in room-details-modal template: ' . $e->getMessage());
    return;
}

    $resort_name = '';
    $categories = wp_get_post_terms($acc_id, 'accommodation-cat', ['parent' => 0]);
    if (!empty($categories) && !is_wp_error($categories)) {
        $resort_name = str_replace(' Accommodation', '', sanitize_text_field($categories[0]->name ?? ''));
    }
?>

<div class="room-modal-header">
    <h3><?php echo esc_html($room_title); ?></h3>
    
    <!-- Action Button -->
    <?php
    if( empty($page_type) || $page_type != 'booking') {
        
        if ( get_post_meta( $acc_id, 'is_price_excluded', true ) && get_post_meta( $acc_id, 'is_price_excluded', true ) === '1' ): ?>
            
            <button bookingPermission="<?php echo $bookingPermission ?>" class="btn enq-btn bedbank_btn" hotel-name="<?php echo esc_attr(get_the_title($acc_id)); ?>" hotel-id="<?php echo esc_attr($property_id); ?>" room-title="<?php echo esc_attr(get_the_title($room_id)); ?>" resort-name="<?php echo esc_attr($resort_name); ?>">Enquire Now</button>
        <?php else : 
            if ($is_roomboss) : 
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
                <button bookingPermission="<?php echo $bookingPermission ?>" is_price_excluded="<?php echo get_post_meta( $acc_id, 'is_price_excluded', true ) ?>" class="btn chk-avl-btn book-btn bedbank_btn" hotel-id="<?php echo esc_attr($property_id); ?>">Request Booking</button>
            <?php endif; ?>
        <?php endif; ?>
        
    <?php } // End page type check ?>
</div>

<!-- Room Gallery -->
<?php if (!empty($gallery)) : 
    $gallery_count = count($gallery);
    $use_bg_images = ($gallery_count > 3);
?>
    <div class="room-modal-gallery js-room-gallery">
        <?php foreach ($gallery as $key => $img_url) : 

            if (empty($img_url)) {
                continue;
            }
        ?>
            <div class="room-slide">
                <a href="<?php echo esc_url($img_url); ?>" data-fancybox="room-gallery-modal-<?php echo $room_id; ?>">    
                    <div class="img <?php echo $use_bg_images ? 'bg-slide' : ''; ?>"
                        <?php if ($use_bg_images) : ?>
                            style="background-image: url('<?php echo esc_url($img_url); ?>');"
                        <?php endif; ?>>
                        <?php if (!$use_bg_images) : ?>
                            <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($room_title); ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Room Overview Info -->
<div class="room-modal-meta">
    <div class="meta-title">Overview</div>
    <div class="room-info">
        <?php if ($guests > 0) : ?>
            <span class="guest"><?php echo esc_html($guests); ?> guests</span>
        <?php endif; ?>

        <?php if ($bedrooms > 0) : ?>
            <span class="bed"><?php echo esc_html($bedrooms); ?> Bed</span>
        <?php endif; ?>

        <?php if ($bathrooms > 0) : ?>
            <span class="bath"><?php echo esc_html($bathrooms); ?> Bath</span>
        <?php endif; ?>

        <?php if (!empty($size)) : ?>
            <span class="sqm"><?php echo esc_html($size); ?> sqm</span>
        <?php endif; ?>
    </div>
</div>

<!-- Room Features -->
<?php if (!empty($features) && is_array($features)) : ?>
    <div class="room-features">
        <div class="meta-title">Room Features</div>
        <ul class="features-list">
            <?php foreach ($features as $feature) : 
                // ✅ Validate feature data
                if (!is_array($feature) || empty($feature['title'])) {
                    continue;
                }
                
                $feature_title = sanitize_text_field($feature['title']);
                if (empty($feature_title)) {
                    continue;
                }
            ?>
                <li><?php echo esc_html($feature_title); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif;

if( $bedding_options && !is_wp_error($bedding_options) ) : ?>
    <div class="bedding-options">
        <div class="meta-title">Bedding Options</div>
        <ul class="options-list">
            <?php foreach ($bedding_options as $option) : 
                // ✅ Validate option data
                if (!is_object($option) || empty( $option->name )) {
                    continue;
                }
                
                $option_title = sanitize_text_field($option->name);
                if (empty($option_title)) {
                    continue;
                }
            ?>
                <li><?php echo esc_html($option_title); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif;

if( $occupency_options && !is_wp_error($occupency_options) ) : ?>
    <div class="bedding-options">
        <div class="meta-title">Occupancy Options</div>
        <ul class="options-list">
            <?php foreach ($occupency_options as $occ_option) : 
                // ✅ Validate option data
                if (!is_array($occ_option) || empty( $occ_option->name )) {
                    continue;
                }
                
                $occ_option_title = sanitize_text_field($occ_option->name);
                if (empty($occ_option_title)) {
                    continue;
                }
            ?>
                <li><?php echo esc_html($occ_option_title); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif;

if ( !empty($room_desc) ) : ?>
    <div class="room-Desc">
        <div class="meta-title">Room Description</div>
        <div class="desc">
            <?php echo esc_html($room_desc); ?>
        </div>
    </div>
<?php endif; ?>