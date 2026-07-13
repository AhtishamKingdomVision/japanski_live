<?php
$top 	= $section['padding_top'];
$bottom = $section['padding_bottom'];
$dats 	= $section['data'];
$class 	= $section['class'];

echo '<section class="full-section html_block '.$class.'" style="padding-top:'.$top.';padding-bottom:'.$bottom.';">';
	echo '<div class="container">';
		echo $dats;
	echo '</div>';
echo '</section>';
?>