<?php 

$top = $section['padding_top'];
$btm = $section['padding_bottom'];
$width = $section['divider_width'];
$height = $section['border_height'];
$color = $section['border_color'];
$style = $section['border_style'];


echo '<section class="full-section divider" style="padding-top: '.$top.'px; padding-bottom: '.$btm.'px;">';
	if($width == 'full'){
		echo '<div class="full-width" style="border-bottom: '.$height.'px '.$style.' '.$color.';"></div>';
	} else{
		echo '<div class="container" style="border-bottom: '.$height.'px '.$style.' '.$color.';"></div>';
	}
echo '</section>';


?>