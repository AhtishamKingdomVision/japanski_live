<?php
$bread = $section['option'];
	if($bread == true):
		echo '<section class="full-section cs_breadcrumbs">';
		    echo '<div class="container">';
		        echo do_shortcode('[wpseo_breadcrumb]');
		    echo '</div>';
		echo '</section>';
	endif;
?>