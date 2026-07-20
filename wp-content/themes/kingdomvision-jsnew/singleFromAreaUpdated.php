<?php
global $post;

$acc_details = get_field('accomodation_details');
$acc_form = $acc_details['accommodation_form'] ?? false;
$address = $acc_details['address'] ?? '';
$latitude = $acc_details['acc_latitude'] ?? get_field('accomodation_details_acc_latitude');
$longitude = $acc_details['acc_longitude'] ?? get_field('accomodation_details_acc_longitude');
$map_query = ($latitude !== '' && $latitude !== null && $longitude !== '' && $longitude !== null)
	? $latitude . ',' . $longitude
	: $address;
$map_embed_url = 'https://www.google.com/maps?q=' . rawurlencode($map_query) . '&z=15&output=embed';
// $acco_image_gallery = get_field('acco_image_gallery');
$acco_image_gallery = kv_get_meta_images_gallery($post->ID, 'acco_pending_images');
$acc_ext_gallary = get_field('acc_gallary', $post->ID);
// @$_GET['gall'] == 'yes' ? pre($acco_image_gallery,1) : '';
$merged_gallary = [];

if( ( isset( $acc_ext_gallary ) && is_array( $acc_ext_gallary ) ) ){
	$merged_gallary = array_merge((array)$acco_image_gallery, (array)$acc_ext_gallary);
	}
else{
	$merged_gallary = !empty( $acco_image_gallery ) ? $acco_image_gallery : [];

}
// @$_GET['gall'] == 'yes' ? pre($merged_gallary) : '';
$bg_color   = $acc_details['bg_color'] ?? '#01111f';
$section_style = $bg_color ? 'style="background-color:' . esc_attr($bg_color) . ';"' : 'xyz';
$ff_image     = get_field('form_footer_image', 'option');
$ff_content   = get_field('form_footer_content', 'option');
echo '<section class="form_area acc-single-banner accSingleBannerUpdate full-section" '. $section_style .' aria-labelledby="acc-title">';
	echo '<div class="container">';
		echo '<div class="left-side">';
			echo '<a class="btn desktop-none quote_toggle" href="javascript:void();">Get a Quote</a>';

			if (function_exists('yoast_breadcrumb')) {
			    $links = apply_filters('wpseo_breadcrumb_links', []);
			    if (!empty($links) && count($links) > 1) {
			        $parent = $links[count($links) - 2];
			        $parentText = $links[count($links) - 3];
			        if (!empty($parent['url']) && !empty($parent['text'])) {

			            echo '<div class="backToResultsWrap">';
			                echo '<a href="' . esc_url($parent['url']) . '" class="backToResults">';
			                    echo '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
			                            <polyline points="15 18 9 12 15 6"></polyline>
			                          </svg> Back to ' . esc_html($parentText['text']) .' results';
			                echo '</a>';
			            echo '</div>'; #backToResultsWrap
			        }
			    }
			}

			echo '<div class="title-wrapper">';
				echo '<h1>'. get_the_title() .'</h1>';
			echo '</div>'; #title-wrapper
			
			if($address){
				echo '<button type="button" class="acc-address acc-address-map-trigger" aria-haspopup="dialog" aria-controls="accommodation-map-modal">';
					echo '<span>'. esc_html($address) .'</span>';
				echo '</button>'; #acc-address
			}

			echo '<div class="acc-gallery t2">';
			if($merged_gallary){
				// pre($merged_gallary,1);
				$count = count($merged_gallary);

				foreach( $merged_gallary as $key => $url ){
					$url = esc_url((string) $url);
					if (empty($url)) {
						continue;
					}
					echo '<div class="galleryItem" data-fancybox="gallery" data-src="'. $url .'">';
						echo '<div class="gi attachment-full size-full is-skeleton" data-bg="'. esc_attr($url) .'"></div>';
					echo '</div>'; #galleryItem
					if($key == 4){
						echo '<span><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> All Photos ('.$count.')</span>';
					}
				}
			}
			else if( has_post_thumbnail( $post->ID ) ){
				$image_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
				echo '<div class="galleryItem">';
				echo '<div class="gi attachment-full size-full is-skeleton" data-bg="'. esc_attr(esc_url($image_url)) .'"></div>';
				echo '</div>'; #galleryItem

			}
			else{
				$room_placeholder_img = get_stylesheet_directory_uri() . '/images/placeholder-listing.jpg';
				echo '<div class="galleryItem">';
				echo '<div class="gi attachment-full size-full is-skeleton" data-bg="'. esc_attr(esc_url($room_placeholder_img)) .'"></div>';
				echo '</div>'; #galleryItem
			}
			echo '</div>'; #acc-gallery


		echo '</div>'; #left-side
	echo '</div>'; #container
echo '</section>'; #acc-single-banner

if ($address && $map_query) {
	echo '<div id="accommodation-map-modal" class="accommodation-map-modal" role="dialog" aria-modal="true" aria-label="' . esc_attr(get_the_title() . ' location map') . '" aria-hidden="true">';
		echo '<div class="accommodation-map-dialog">';
			echo '<button type="button" class="accommodation-map-close" aria-label="Close map">&times;</button>';
			echo '<iframe class="accommodation-map-frame" title="' . esc_attr(get_the_title() . ' location') . '" data-src="' . esc_url($map_embed_url) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
		echo '</div>';
	echo '</div>';
}

?>

<script>
	jQuery(document).ready(function($) {
	var $mapModal = $('#accommodation-map-modal');
	var $mapTrigger = $('.acc-address-map-trigger');

	function openAccommodationMap() {
		if (!$mapModal.length) return;

		var $frame = $mapModal.find('.accommodation-map-frame');
		if (!$frame.attr('src')) {
			$frame.attr('src', $frame.data('src'));
		}

		$mapModal.addClass('is-visible').attr('aria-hidden', 'false');
		$('body').addClass('accommodation-map-open');
		$mapModal.find('.accommodation-map-close').trigger('focus');
	}

	function closeAccommodationMap() {
		if (!$mapModal.hasClass('is-visible')) return;

		$mapModal.removeClass('is-visible').attr('aria-hidden', 'true');
		$('body').removeClass('accommodation-map-open');
		$mapTrigger.trigger('focus');
	}

	$mapTrigger.on('click', openAccommodationMap);
	$mapModal.on('click', function(event) {
		if (event.target === this || $(event.target).hasClass('accommodation-map-close')) {
			closeAccommodationMap();
		}
	});
	$(document).on('keydown', function(event) {
		if (event.key === 'Escape') {
			closeAccommodationMap();
		}
	});

    if ($(".cta_with_bg_image").length > 0) {
        $(".cta_with_bg_image").each(function (i) {
            $(this).removeAttr('id').attr('id', 'cta_with_bg_image' + i);
        });
    }

		$('.accGallerySlider.activeSliderX').slick({
		  dots: false,
		  arrows: true,
		  infinite: true,
		  speed: 300,
		  slidesToShow: 3,
		  slidesToScroll: 1,
		  prevArrow: '<button type="button" class="slick-prev"><img src="<?php echo THEME_URL ?>/images/left_arrow.svg" alt="Previous"></button>',
          nextArrow: '<button type="button" class="slick-next"><img src="<?php echo THEME_URL ?>/images/right_arrow.svg" alt="Next"></button>',
		  responsive: [
		    {
		      breakpoint: 1024,
		      settings: {
		        slidesToShow: 3,
		        slidesToScroll: 1,
		      }
		    },
		    {
		      breakpoint: 991,
		      settings: {
		        slidesToShow: 2,
		        slidesToScroll: 1,
		      }
		    },
		    {
		      breakpoint: 768,
		      settings: {
		        slidesToShow: 1,
		        slidesToScroll: 1
		      }
		    }
		  ]
		});

		// ================================ accGallery =====================
	    $('[data-fancybox="accGallery"]').fancybox({
	      selector: '.slick-slide:not(.slick-cloned) a'
	    });
	    $('.slick-slider').on('init', function(event, slick) {
	      $('.slick-cloned a').removeAttr('data-fancybox');
	    });
	    // ================================ accGallery =====================
	})
</script>