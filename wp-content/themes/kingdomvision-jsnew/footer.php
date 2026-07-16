<?php
$footer_logo         = get_field('footer_logo', 'option');
$footer_content      = get_field('footer_content', 'option');
$menu                = get_field('menu', 'option');
$mini_content        = get_field('mini_content', 'option');
$copyright           = get_field('copyright', 'option');
$footer_bottom_logo  = get_field('footer_bottom_logo', 'option');

echo '<footer class="footer_main full-section" role="contentinfo">';

    echo '<div class="container">';
        echo '<div class="footer_inner">';

            // LEFT COLUMN CONTENT
            echo '<div class="footer_content foo_col">';

                if ( $footer_logo ) {
                    echo wp_get_attachment_image($footer_logo,'full',false,array('alt' => esc_attr( get_bloginfo('name') . ' footer logo' )));
                }

                if ( ! empty($footer_content) ) {
                    echo wp_kses_post( $footer_content );
                }

            echo '</div>'; // footer_content

            // FOOTER MENU
            echo '<div class="footer_menu foo_col">';

                if ( ! empty($menu) && is_array($menu) ) {

                    foreach ( $menu as $value ) {

                        $menu_title = $value['menu_title'] ?? '';
                        $menu_links = $value['menu_link'] ?? [];

                        echo '<div class="menu_col">';

                            if ( $menu_title ) {
                                echo '<h3 class="footer-accordion-title">' . esc_html( $menu_title ) . '</h3>';
                            }

                            echo '<ul class="footer-accordion-content">';

                                if ( is_array($menu_links) ) {
                                    foreach ( $menu_links as $item ) {

                                        $mlink        = $item['mlink'] ?? [];
                                        $link_url     = esc_url( $mlink['url'] ?? '#' );
                                        $link_title   = esc_html( $mlink['title'] ?? '' );
                                        $link_target  = esc_attr( $mlink['target'] ?? '_self' );

                                        echo '<li><a href="' . $link_url . '" target="' . $link_target . '">' . $link_title . '</a></li>';
                                    }
                                }

                            echo '</ul>';

                        echo '</div>';
                    }
                }

                echo '<div class="iata_container">';
                    echo '<div class="iata_content">';
                        $iata_title = get_field('footer_iata_title', 'option');
                        $iata_content = get_field('footer_iata_body', 'option');
                        if ( $iata_title ) {
                            echo '<h3>' . esc_html( $iata_title ) . '</h3>';
                        }
                        if ( $iata_content ) {
                            echo '<p>' . esc_html( $iata_content ) . '</p>';
                        }
                    echo '</div>'; // iata_content
                    echo '<div class="iata_logo">';
                        $iata_logo = get_field('footer_iata_logo', 'option');
                        if ( $iata_logo ) {
                            echo wp_get_attachment_image($iata_logo,'full',false,array('alt' => esc_attr( get_bloginfo('name') . ' iata logo' )));
                        }
                    echo '</div>'; // iata_logo
                echo '</div>'; // iata_container
            echo '</div>'; // footer_menu

        echo '</div>'; // footer_inner
    echo '</div>';

    // COPYRIGHT AREA
    echo '<div class="copyright">';
		echo '<div class="container">';
		    echo '<div class="left_content">';

		        if ( ! empty($copyright) ) {
		            echo '<p>' . esc_html( $copyright ) . '</p>';
		        }

		    echo '</div>';

		    echo '<div class="right_content">';

		        if (!empty($footer_bottom_logo) && is_array($footer_bottom_logo)){
		        	echo '<div class="f_logo">';
			        	foreach ($footer_bottom_logo as $value) {
			        		$flogo = $value['flogo'];
			                echo wp_get_attachment_image($flogo,'full',false,array('alt' => esc_attr( get_bloginfo('name') . ' footer bottom logo' )));
			        	}
		        	echo '</div>'; //f_logo
		        }
		        if ( ! empty($mini_content) ) {
		            echo '<p>' . esc_html( $mini_content ) . '</p>';
		        }


		    echo '</div>';
		echo '</div>';
    echo '</div>'; //copyright

echo '</footer>';

// child age popup
    include('child-age.php');
// child age popup End

/* Sticky CTA Repeater Logic */
$sticky_ctas = get_field('sticky_cta', 'options');
$matched_cta = null;

if ($sticky_ctas) {
    $current_pt = get_post_type();
    foreach ($sticky_ctas as $row) {
        if (isset($row['post_type']) && $row['post_type'] === $current_pt) {
            // If post type is 'page', apply specific conditions: front page or accommodation path
            if ($current_pt !== 'page' || is_accommodation() || is_front_page()) {
                $matched_cta = $row['footer_cta_link'];
                break;
            }
        }
    }
}
$attr = '';
if( !empty($matched_cta) ){ 
    $attr = $current_pt == 'accommodation' ? 'class="sticky-cta-btn enq-btn" hotel-name="'.get_the_title().'" room-title=""': 'class="sticky-cta-btn" href="'.esc_url($matched_cta['url']).'"';
    ?>
    <!-- This is the Sticky CTA HTML -->
    <div class="sticky-cta-container">
        <a target="<?php echo esc_attr($matched_cta['target'] ?: '_self'); ?>" <?php  echo $attr;?>><?php echo esc_html($matched_cta['title']); ?></a>
    </div>
<?php } ?>

    <div class="sticky-cart-container foo-cart">
        <a href="" class="sticky-cta-btn" ><i class="fa-solid fa-basket-shopping"></i><label class="item-count" >0</label></a>
    </div>

<?php
echo '</div>'; //Main Wrapper

echo '<div class="mobFilterModal" id="mobFilterModal">';
  echo '<div class="mobFilterInner">';
    echo '<div class="mobFilterClose">';
      echo '<a href="javascript:;" class="closeMobSearch">✕</a>';
    echo '</div>'; #mobFilterClose
    echo '<div class="resortFilterWrap">';
        echo do_shortcode('[newResortFilters]');
    echo '</div>'; #resortFilterWrap
  echo '</div>'; #mobFilterInner
echo '</div>'; #mobFilterModal

if( is_page( [ 'accommodation', 'hotels', 'deals', 'onsen', 'chalets', 'ski-in-ski-out' ] ) || is_singular( 'accommodation' ) ){

    echo '<div id="Enquiry-modal" class="Enquiry-modal">
        <div class="Enquiry-modal-overlay"></div>

        <div class="Enquiry-modal-content">
            <a class="Enquiry-modal-close" id="close-Enquiry-info">x</a>
            <h3 class="Enquiry-modal-title">Enquiry Now</h3>
            <div class="Enquiry-modal-form-slot">' . get_enquiry_form_html('[gravityform id="1" title="false" ajax="true"]') . '</div>
        </div>
    </div>';
}

if ( is_singular( 'accommodation' ) ) {
    $post_id = get_the_ID();
    $property_id = get_field( 'property_id', $post_id );
    if( get_field("acc_location_info") ){
        echo '<div id="location-info-modal" class="location-modal">
            <div class="location-modal-overlay"></div>
    
            <div class="location-modal-content">
                <a class="location-modal-close" id="close-location-info">×</a>
    
                <h3>Location Info</h3>
    
                <div class="location-modal-body">
                    ' . wp_kses_post(get_field("acc_location_info")) . '
                </div>
            </div>
        </div>';
    }

    echo '<div id="room-search-popup-modal" class="room-search-popup-modal">
        <div class="room-search-popup-overlay"></div>

        <div class="room-search-popup-content">
            <button type="button" class="room-search-popup-close" id="close-room-search-popup">×</button>

            <div class="room-search">
                <form 
                    id="room-filter-form-popup" 
                    method="POST" 
                    property-id="' . esc_attr($property_id) . '" 
                    acc-id="' . esc_attr($post_id) . '" 
                    room-id="' . esc_attr($post_id) . '"
                >
                    <div class="search-fields">

                        <div class="field">
                            <label for="sc-check-in-popup">Check In</label>
                            <input 
                                type="text" 
                                id="sc-check-in-popup" 
                                class="sv-check-in" 
                                name="checkin" 
                                autocomplete="off" 
                                placeholder="Enter your check in dates" 
                                required
                                readonly >
                        </div>

                        <div class="field">
                            <label for="sc-check-out-popup">Check Out</label>
                            <input 
                                type="text" 
                                id="sc-check-out-popup" 
                                class="sv-check-out" 
                                name="checkout" 
                                autocomplete="off" 
                                placeholder="Enter your check out dates" 
                                required
                                readonly
                            >
                        </div>

                        <div class="field">
                            <label for="sc-guests-popup">Guests</label>
                            <input 
                            type="text" 
                            id="sc-guests-popup" 
                            class="sv-guests" 
                            name="guets" 
                            autocomplete="off" 
                            placeholder="Click to add guests" 
                            required
                        >
                    </div>

                    <div class="room-filter-guests-popover" id="room-filter-guests-popover-popup" role="dialog">

                        <div class="g-row">
                            <div>
                                <span class="g-label">Adults</span>
                                <span class="g-sub">Age 16+</span>
                            </div>
                            <div class="g-counter">
                                <button type="button" class="g-btn js-btn-adults-minus" disabled>−</button>
                                <span class="g-val js-v-adults">2</span>
                                <button type="button" class="g-btn js-btn-adults-plus">+</button>
                            </div>
                        </div>

                        <div class="g-row">
                            <div>
                                <span class="g-label">Children</span>
                                <span class="g-sub">Ages 3–15</span>
                            </div>
                            <div class="g-counter">
                                <button type="button" class="g-btn js-btn-children-minus" disabled>−</button>
                                <span class="g-val js-v-children">0</span>
                                <button type="button" class="g-btn js-btn-children-plus">+</button>
                            </div>
                        </div>

                        <div class="g-row">
                            <div>
                                <span class="g-label">Infants</span>
                                <span class="g-sub">Ages 0–2</span>
                            </div>
                            <div class="g-counter">
                                <button type="button" class="g-btn js-btn-infants-minus" disabled>−</button>
                                <span class="g-val js-v-infants">0</span>
                                <button type="button" class="g-btn js-btn-infants-plus">+</button>
                            </div>
                        </div>

                    </div>
                </div>

                <button type="submit" class="upd-guest-btn">Update Guests</button>
            </form>
        </div>
    </div>
    </div>';
}
/* terms popup */
if ( is_page_template( 'booking_confirmation.php' ) || is_page( 26812 ) ) {
    echo '<div id="terms-modal" class="terms-modal" style="display: none;">
        <div class="terms-modal-content">
            <button class="terms-modal-close">&times;</button>
            <div id="terms-modal-body"></div>
        </div>
    </div>';
}
/* terms popup */
wp_footer();
?>
</body>
</html>