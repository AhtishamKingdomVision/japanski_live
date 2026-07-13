<?php 
echo '<header class="full-section mobile-header">';
	echo '<div class="container">';
		if(has_nav_menu('mobile-menu')){
			echo '<div class="menu-humberger">';
				echo '<i class="fa fa-bars" aria-hidden="true"></i>';
			echo '</div>';
		}
		$logo = get_field('logo_image' , 'option');
		if($logo){
			echo '<div class="logo">';
				echo '<a href="'.home_url().'">';
					echo '<img src="'.$logo.'" />';
				echo '</a>';
			echo '</div>';
		}
		$search = get_field('mobile_search' , 'option');
		if($search == true){
			echo '<div class="mob-search">';
				echo '<a href="javascript:;">';
					echo '<img src="'.home_url().'/wp-content/uploads/2022/03/search-upd.svg" width="24" height="24" />';
				echo '</a>';
			echo '</div>';
		}
	echo '</div>';
echo '</header>';

if(has_nav_menu('mobile-menu')){
	echo '<div class="full-section mobile-menu">';
		echo '<div class="container">';
		wp_nav_menu(array(
			'theme_location' => 'mobile-menu',
			'çontainer_class' => 'mob-menu'
		));
		echo '</div>';
	echo '</div>';
}
?>