<?php 

$option 	= $section['map_option'];
$map_img 	= $section['map_image'];
$content 	= $section['content'];
$sec_ID 	= $section['section_id'];
$title 		= $section['title'];
$hestyle 	= 'style="width:100%;text-align:center;"';
echo '<section class="full-section map-with-content" '.( $sec_ID ? 'id="'.$sec_ID.'"' : '' ).'>';
	echo '<div class="container '.$option.'">';
		if($title){ printf('<h2 %s style="">%s</h2>', $hestyle, $title); }
		if($map_img){
			echo'<div class="map">';
				echo '<a href="'.$map_img.'" data-fancybox="'.$map_img.'">';
					echo '<img src="'.$map_img.'" alt="Map"/>';
				echo '</a>';
			echo'</div>';
		}
		if($content){
			echo '<div class="content">';
				echo $content;
			echo '</div>';
		}
	echo '</div>';
echo '</section>';

?>