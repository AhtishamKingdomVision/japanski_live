<?php
$main_title  = $section['main_title'];
$boxes_area  = $section['boxes_area'];

echo '<section class="full-section five_boxes">';
	echo '<div class="container">';
		if(!empty($boxes_area)):
			foreach ($boxes_area as $key => $value) {
			$columns = $value['columns'];
			$boxes   = $value['boxes'];
				if(!empty($boxes)):
					echo '<ul class="'.$columns.'">';
						foreach ($boxes as $key => $child) {
						$image    	= $child['image'];
						$title    	= $child['title'];
						$option   	= $child['action_option'];
						$pop_btn  	= $child['pop_btn_label'];
						$link     	= $child['link'];
						$content  	= $child['content'];
						$more  		= $child['more_content'];

							echo '<li>';
								echo '<div class="img" style="background: url('.wp_get_attachment_image_url($image, 'full').')no-repeat center/cover">';
									if($option == 'link'){
										if($link):
											$link_url 	= $link['url'];
											$link_targe = $link['target'] ? $link['target'] : '_self';
											printf('<a class="invisible" href="%s" target="%s"></a>', $link_url, $link_targe);
										endif;
									} elseif ($option == 'popup') {
											echo sprintf('<a class="invisible" href="javascript:;" data-fancybox data-src="#hidden-content-'.$key.'"></a>');
									}
								echo '</div>'; //img
								echo '<div class="content">';
									if($title){ printf('<h3>%s</h3>', $title); }
									echo $content;
									if($option == 'popup'){
										echo '<div class="more_text" style="display: none;" id="hidden-content-'.$key.'">';
											//echo $content;
											echo $more;
										echo '</div>';
									}
									if($option == 'link'){
										if($link):
											$link_url 	= $link['url'];
											$link_title = $link['title'];
											$link_targe = $link['target'] ? $link['target'] : '_self';
											printf('<a class="btn btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_targe, $link_title);
										endif;
									} elseif ($option == 'popup') {
											echo sprintf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#hidden-content-'.$key.'">%s</a>', $pop_btn);
									}
								echo '</div>';
							echo '</li>';
						}
					echo '</ul>';
				endif;
			}
		endif;
	echo '</div>';
echo '</section>';
?>