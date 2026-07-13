<?php
$main_title = $section['main_title'];
$boxes 		= $section['boxes'];

echo '<section class="full-section six_boxes_with_title">';
	echo '<div class="container">';
		echo '<div class="sbwt__inn">';
			if($main_title){ printf('<h2>%s</h2>', $main_title); }
			if(!empty($boxes)):
				echo '<div class="sbwt_cover">';
					foreach ($boxes as $key => $value) {
					$image = $value['image'];
					$title = $value['title'];
					$link = $value['link'];

						echo '<div class="sbwt_list">';
							if($link){
							$link_url 	= $link['url'];
							$link_targe = $link['target'] ? $link['target'] : '_self';
								printf('<a href="%s" target="%s">', $link_url, $link_targe);
									echo wp_get_attachment_image($image, 'full');
									if($title){ printf('<h3>%s</h3>', $title); }
								echo '</a>';
							} else {
								echo wp_get_attachment_image($image, 'full');
								if($title){ printf('<h3>%s</h3>', $title); }								
							}
						echo '</div>'; //sbwt_list
					}
				echo '</div>'; //sbwt_cover
			endif;
		echo '</div>'; //sbwt__inn
	echo '</div>';
echo '</section>';
?>