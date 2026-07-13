<?php get_header();
 /* 
 * The default page template
 */
 $award_image = get_field('award_image', 'option');
 $award_opt   = get_field('opt', 'option');
 ?>
<div class="content-wrapper full-section">
<?php
	// pre( @$_GET['_devv'] == 'hamza' ? var_dump( $award_opt ) : '');
	if($award_opt === true){
			echo '<div class="award_badge">';
				echo wp_get_attachment_image($award_image, 'full');
			echo '</div>'; //award_badge
	}

	// Theme Option Field
	$opt_img 		= get_field('image_option' , 'option');
	$opt_title 		= get_field('title_option' , 'option');
	$opt_ID 		= @$_GET['new_form'] != 'show' ? get_field('form_id_option' , 'option') : '4';
	$opt_btn 		= get_field('learn_more' , 'option');
	$opt_text 		= get_field('more_text_option' , 'option');

	// Page Banner Field
	$ban_img 	= get_field('image');
	$ban_title	= get_field('title');
	$ban_show 	= get_field('showhide');
	$overlay 	= get_field('overlay');
	$form_title  = get_field('form_title');
$toggle     = get_field('toggle_form');
if($toggle == true){
	$form_s = 'form_toggle';
	$hide_form = 'desktop-none';
} else {
	$form_s = '';
	$hide_form = '';
}

if($form_title == ''){
	$margin = 'space_bottom';
} else {
	$margin = '';	
}
	if($ban_show == false){
		// echo '<section class="full-section top_banner kv-layzy" id="top_sec" data-background="'.( $ban_img ? $ban_img : $opt_img ).'" style="background-repeat:no-repeat ; back-position:center ; background-size:cover ; z-index: 1 ;">';
		echo '<section class="full-section top_banner" id="top_sec" style="background-image: url('.( $ban_img ? $ban_img : $opt_img ).');background-repeat:no-repeat ; background-position:center ; background-size:cover ; z-index: 1 ;">';

			echo '<div class="container">';

				if($ban_title){
					printf('<h1 class="'.$margin.'">%s</h1>',$ban_title);
					if($form_title){
						echo '<h3 class="'. $form_s .' mobile-none"><a href="javascript:;">' . $form_title . '</a></h3>';
					}
				} else {
					printf('<h1 class="'.$margin.'">%s</h1>',$opt_title);
					if($form_title){
						echo '<h3 class="'. $form_s .' mobile-none"><a href="javascript:;">' . $form_title . '</a></h3>';
					}
				}
				echo '<a class="quote_form desktop-none" href="'. home_url() .'/get-a-quote">GET A QUOTE</a>';

			echo '<div class="enquire_form mobile-none '. $hide_form .'">';
				// echo '<a class="enquire_close desktop-none" href="javascript:;"><i class="fa fa-close"></i></a>';
				if($opt_text):
					echo '<a class="fm_open" href="javascript:;">'.$opt_btn.'</a>';
				endif;
					echo do_shortcode('[gravityform id="'.$opt_ID.'" title="true" description="false" ajax="true"]');
				if($opt_text):
					echo '<div class="more_text" style="display:none;">';
						echo '<a class="fm_close" href="javascript:;"><i class="fa fa-close"></i></a>';
						echo $opt_text;
					echo '</div>'; //more_text
				endif;
			echo '</div>'; //enquire_form
		
			echo '</div>'; //container
		
		echo '</section>';
	}
	if(get_field('breadcrumbs_option') == true):
        echo '<section class="full-section cs_breadcrumbs">';
	        echo '<div class="container">';
		        // echo do_shortcode('[cst-breadcrumbs]');
		        echo do_shortcode('[wpseo_breadcrumb]');
	        echo '</div>';
        echo '</section>';
	endif;

	if( get_field('page_builder') ){
		$page_builder = get_field('page_builder');
		foreach ($page_builder as $key => $section) {
			include('inc/inc-'.$section['acf_fc_layout'].'.php');
		}
	} else {
		echo '<div class="container" style="text-align:center;">';
			echo '<h2 style="margin:20px 0 20px;display:inline-block;">';
				echo 'Fields Not Founds';
			echo '</h2>';
		echo '</div>';	
	} 
	?>
</div>

<style type="text/css">
<?php if($overlay == true): ?>
  section.top_banner:after { content: ""; position: absolute; width: 100%; height: 100%; top: 0; background: rgb(0 0 0 / 50%); z-index: -1; }
<?php endif; ?>
</style>

<?php get_footer(); ?>