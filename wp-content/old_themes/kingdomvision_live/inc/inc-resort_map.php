<?php
$title 		  = $section['title'];
$content 	  = $section['content'];
$more_content = $section['more_content'];
$images   	  = $section['images'];

echo '<section class="full-section resort_map">';
	echo '<div class="container">';
		echo '<div class="rm__inn">';
			if($title){ printf('<h2>%s</h2>', $title); }
			if($content){ printf('<div class="rm__con">%s</div>', $content); }
			if(!empty($images)):
				echo '<div class="map_image">';
					foreach ($images as $key => $value) {
					$thumbnail = $value['thumbnail'];
					$large = $value['large'];
						echo '<div class="map_inn">';
							echo '<a data-fancybox="trigger-element-gallery" href="'.$large.'">';
								echo '<img class="rounded" src="'.$thumbnail.'" />';
							echo '</a>';
						echo '</div>';
					}
				echo '</div>'; //map_image
			endif;
			if($more_content){ printf('<div class="rm__con_mor">%s</div>', $more_content); }
		echo '</div>'; //rm__inn
	echo '</div>';
echo '</section>';
?>