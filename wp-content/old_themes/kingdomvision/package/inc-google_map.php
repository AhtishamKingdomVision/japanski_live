<?php 

$title 	   = $section['title'];
$sub_title = $section['sub_title'];
$iframe    = $section['iframe'];
$latitude  = $section['latitude'];
$longitude = $section['longitude'];
$sec_ID    = $section['section_id'];

echo '<section class="full-section google-map" '.($sec_ID ? 'id="'.$sec_ID.'"' : '').'>';
	echo '<div class="container">';
		if($title){
			echo '<h2>'.$title.'</h2>';
		}
		if($sub_title){
			echo '<p>'.$sub_title.'</p>';
		}
		if($latitude && $longitude):
			echo '<iframe width="100%" height="600" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q='.$latitude.','.$longitude.'&hl=en&z=14&amp;output=embed"></iframe>';
		endif;
		if($iframe){
			echo '<div class="maps">';
				echo $iframe;
			echo '</div>';
		}
	echo '</div>';
echo '</section>';

?>