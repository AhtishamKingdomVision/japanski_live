<?php
$vot_image 	  = get_field('vot_image', 'option');
$vote_link 	  = get_field('vote_link', 'option');
$vote_content = get_field('vote_content', 'option');
$vote_option  = get_field('vote_option', 'option');
$link_url = $vote_link['url'];
$link_title = $vote_link['title'];
$link_target = $vote_link['target'] ? $vote_link['target'] : '_self';

if($vote_option == true):
	echo '<section class="vote_for_us hide">';
		echo '<div class="container">';
			echo '<div class="vfu_inn">';
				echo '<a class="vote_close" href="javascript:;"><i class="fa fa-close"></i></a>';
				echo '<div class="vfu_con">'. $vote_content .'</div>';
				echo '<div class="vfu_img">';
					echo '<a href="'. $link_url .'" target="'. $link_target .'">';
						echo wp_get_attachment_image($vot_image, 'full');
					echo '</a>';
				echo '</div>'; //vfu_img
			echo '</div>'; //vfu_inn
		echo '</div>';
	echo '</section>';
endif;

$foo_top  = get_field('footer_top_hide' , 'option');

$bg_img = get_field('footer_background' , 'option');
$footer_one = get_field('footer_top' , 'option');
$footer_two = get_field('footer_bottom' , 'option');

$copy 	= get_field('copyright_text' , 'option');
$dev 	= get_field('develop_text' , 'option');

$link  = get_field('button', 'option');
$content = get_field('content', 'option');

$float = get_field('fl_hide_show', 'option');

if($float):
	echo '<section class="full-section floating">';
		echo '<div class="container">';
			echo '<a class="close_flo" href="javascript:;"><i class="fa fa-close"></i></a>';
		// if (@$_GET['kv'] == 'dev') {
			echo '<div class="floating_in">';
			if($content){printf('<div class="floating_content">%s</div>', $content);}
				echo do_shortcode('[gravityform id="3" title="false" description="false" ajax="true"]');
			echo '</div>'; //floating_inn
		// } else {
		// 	echo '<div class="floating_inn">';
		// 		if($content){printf('<div class="floating_content">%s</div>', $content);}
		// 		if( $link ): 
		// 		    $link_url = $link['url'];
		// 		    $link_title = $link['title'];
		// 		    $link_target = $link['target'] ? $link['target'] : '_self';
	    // 			echo '<a class="floating_link" href="'.$link_url.'" target="'.$link_target.'">'.$link_title.'</a>';
		// 		endif;
		// 	echo '</div>'; //floating_inn
		// }
		echo '</div>';
	echo '</section>'; //floating
endif;

echo '<footer class="full-section footer-wrapper" style="background-image: url('. $bg_img .');background-repeat: no-repeat; background-position: center; background-size: cover;">';
	echo '<div class="footer">';
		echo '<div class="container">';
				if($foo_top == true){
					if($footer_one['top_logo'] || $footer_one['top_content']){
						echo '<div class="footer-top">';
							echo '<div class="top-logo">';
								echo '<img src="'.$footer_one['top_logo'].'" />';
							echo '</div>'; //top-logo
							echo '<div class="top-content">';
								echo $footer_one['top_content'];
							echo '</div>'; //top-content
						echo '</div>'; //footer-top
					}
				}
				echo '<div class="footer-bottom">';
					if($footer_two['btm_logo']){
					echo '<div class="left">';
						echo '<img src="'.esc_url( $footer_two['btm_logo']['url'] ).'" alt="'.esc_attr( $footer_two['btm_logo']['alt'] ).'" />';
					echo '</div>'; #left
					}
					if($footer_two['btm_text'] || $footer_two['btm_menu']){
					echo '<div class="middle">';
						if($footer_two['btm_text']){
							echo '<p>'.$footer_two['btm_text'].'</p>';
						}
						if($footer_two['btm_menu']){
							echo '<ul class="footer-menu">';
								foreach ($footer_two['btm_menu'] as $key => $menu) {
									$m_link = $menu['link'];

									$url = $m_link['url'];
								    $title = $m_link['title'];
								    $target = $m_link['target'] ? $m_link['target'] : '_self';

									echo '<li><a href="'.$url.'" target="'.$target.'">'.$title.'</a></li>'; #li

								}
							echo '</ul>'; #footer-menu
						}
						if($copy){
							echo '<div class="copyright">';
								echo '<p>'.$copy.'</p>';
								//echo '<p>'.$dev.'</p>';
							echo '</div>';
						}
					echo '</div>'; #middle
					}
					if($footer_two['social_icon'] || $footer_two['phone_number']){
					echo '<div class="right">';
						echo '<div class="foo-right-logo">';
							if($footer_one['right_link']){
								printf('<a href="%s" target="_blank">', $footer_one['right_link']);
								echo wp_get_attachment_image($footer_one['logo_right'], 'full');
							} else {
								printf('<a href="javascript:;">');
								echo wp_get_attachment_image($footer_one['logo_right'], 'full');
							}
							echo '</a>';
						echo '</div>'; //foo-right-logo
					echo '</div>'; #right
					}
				echo '</div>'; #footer-bottom
				
		echo '</div>'; #container
	echo '</div>'; #footer
	echo '<div class="bottom_line">';
		if($footer_two['social_icon']){
			echo '<ul class="social-icon">';
				foreach ($footer_two['social_icon'] as $key => $social) {
					$icon_sel = $social['icon_selection'];
					$icon_link = $social['icon_link'];

					echo '<li><a href="'.$icon_link.'" target="_blank"><i class="fa '.$icon_sel.'"></i></a></li>';
				}
			echo '</ul>'; #social-icon
		}
		if($footer_two['phone_number']){
			echo '<ul class="phone-num">';
				foreach ($footer_two['phone_number'] as $key => $number) {
					$cont_name = $number['country_name'];
					$cont_number = $number['country_number'];

					echo '<li><span>'.$cont_name.'</span>: <a href="tel:'.$cont_number.'">'.$cont_number.'</a></li>';
				}
			echo '</ul>'; #phone-num
		}
	echo '</div>';//bottom_line

	$enquire = get_field('show_enquire');

	if($enquire == false){
		$_ref_url = $_SESSION['referral_url'] = get_the_permalink( );
		echo '<div class="enquire-btn"><a href="'. home_url() .'/get-a-quote/">Enquire</a></div>';
		echo '<div class="jumping"><a href="#jump"><i class="fa fa-arrow-up"></i></a></div>';
	}
	
echo '</footer>';


// child age popup
	include('child-age.php');
// child age popup End

// if (@$_GET['kv'] == 'dev') {
$pop_option  = get_field('pop_option', 'option');
$pop_title   = get_field('site_pop_title', 'option');
$pop_content = get_field('site_pop_content', 'option');
$exclude 	 = get_field('exclude_page', 'option');

$ids = wp_list_pluck($exclude, 'ID');
$cls = in_array(get_the_ID(), $ids) ? 'hidden' : '';
	if($pop_option == true):
		// Front Popup
			echo '<section class="site_popup '.$cls.'">';
				echo '<div class="site_popup_inn">';
					echo '<a class="close_pop" href="javascript:;"><i class="fa fa-close"></i></a>';
					echo '<div class="site_popup_content">';
						if($pop_title){printf('<h2>%s</h2>', $pop_title);}
						if($pop_content){printf('<div class="pop_con">%s</div>', $pop_content);}
					echo '</div>'; //site_popup_content
				echo '</div>'; //site_popup_inn
			echo '</section>'; //site_popup
		// Front Popup End
	endif;
// }
echo '</div>'; //Main Wrapper
wp_footer();
?>
</body>
</html>