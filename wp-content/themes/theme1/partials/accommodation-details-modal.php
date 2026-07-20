<?php
/**
 * Accommodation Details Modal Template
 * Displays detailed accommodation/property information including gallery, description, and facilities
 * Used in modal popups for accommodation details on the booking page
 * 
 * Required $args keys:
 *   - property_id: Numeric property/accommodation ID (booking system ID)
 */

try {
    // ✅ STEP 1: Validate and extract arguments
    $args = is_array($args) ? $args : [];
    // var_dump($args);
    $property_id = ($args['property_id'] ?? 0);

    // Validate property ID is required and positive
    if (empty($property_id)) {
        return;
    }

    // ✅ STEP 2: Resolve WordPress post ID from booking system property ID
    $acc_id = get_post_id_by_typeId($property_id, 'accommodation');
    // var_dump($acc_id);
    if (empty($acc_id)) {
        return;
    }

    // ✅ STEP 3: Get and validate accommodation post
    $post = get_post($acc_id);
    if (empty($post) || is_wp_error($post)) {
        return;
    }

    $property_title = get_the_title($acc_id);
    // $property_desc = get_field('client_quote_desc', $acc_id) ?: get_field('quote_desc', $acc_id) ?: '';
    $property_desc = get_field( 'client_quote_desc', $acc_id ) ?? get_field( 'quote_desc', $acc_id );
    // $location = get_field('acc_location_info', $acc_id) ?: '';
    $acc_details = get_field('accomodation_details');
    $address = @$acc_details['address'] ?? '';

    // ✅ STEP 4: Get gallery images
    $gallery = kv_get_meta_images_gallery($acc_id, 'acco_pending_images');
    $gallery = (!empty($gallery) && is_array($gallery)) ? $gallery : [];

    // Add featured image to gallery if available
    $featured_image = get_the_post_thumbnail_url($acc_id, 'large');
    if ($featured_image) {
        array_unshift($gallery, $featured_image);
    }

    // ✅ STEP 5: Get facilities (property amenities)
    $facilities = get_the_terms($acc_id, 'property_ammenites');
    if (is_wp_error($facilities) || empty($facilities)) {
        $facilities = [];
    }

} catch (Exception $e) {
    error_log('Error in accommodation-details-modal template: ' . $e->getMessage());
    return;
}
?>

<div class="room-modal-header">
    <h3><?php echo esc_html($property_title); ?></h3>
</div>

<!-- Property Gallery -->
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
                <a href="<?php echo esc_url($img_url); ?>" data-fancybox="accommodation-gallery-modal-<?php echo $acc_id; ?>">    
                    <div class="img <?php echo $use_bg_images ? 'bg-slide' : ''; ?>"
                        <?php if ($use_bg_images) : ?>
                            style="background-image: url('<?php echo esc_url($img_url); ?>');"
                        <?php endif; ?>>
                        <?php if (!$use_bg_images) : ?>
                            <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($property_title); ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Location -->
<?php if (!empty($address)) : ?>
    <div class="room-modal-meta">
        <div class="meta-title">Location</div>
        <div class="room-info">
            <span class="location"><?php echo esc_html($address); ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Description -->
<?php if (!empty($property_desc)) : ?>
    <div class="room-modal-description">
        <div class="meta-title">Description</div>
        <div class="description-content" style="color: #00111F; padding-left: 25px;">
            <?php echo wp_kses_post($property_desc); ?>
        </div>
    </div>
<?php endif; ?>

<!-- Facilities -->
<?php if (!empty($facilities) && is_array($facilities)) : ?>
    <div class="room-features">
        <div class="meta-title">Facilities</div>
        <ul class="features-list">
            <?php foreach ($facilities as $facility) : 
                if (!is_object($facility) || empty($facility->name)) {
                    continue;
                }
                $facility_name = sanitize_text_field($facility->name);
                if (empty($facility_name)) {
                    continue;
                }
            ?>
                <li><?php echo esc_html($facility_name); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
