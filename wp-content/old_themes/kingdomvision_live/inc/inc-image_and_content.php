<?php
$main_title = $section['main_title'];
$iac 		= $section['iac'];

echo '<section class="full-section image_and_content">';
	echo '<div class="container">';
		if(!empty($iac)):
			foreach ($iac as $key => $value) {
			$align 		= $value['image_position'];
			$image 		= $value['image'];
			$title 		= $value['title'];
			$option		= $value['action_option'];
			$pop_btn	= $value['pop_btn_label'];
			$link  		= $value['link'];
			$content 	= $value['content'];
			$more 		= $value['more_content'];
				echo '<div class="iac_cover '.$align.'">';
					echo '<div class="iac_image">';
						echo wp_get_attachment_image($image, 'full');
						// $image_alt = $attachment->post_title;
					echo '</div>';
					echo '<div class="iac_content">';
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
								printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_targe, $link_title);
							endif;
						} elseif ($option == 'popup') {
							echo sprintf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#hidden-content-'.$key.'">%s</a>', $pop_btn);
						}
					echo '</div>'; //iac_content
				echo '</div>'; //iac_cover
			}
		endif;
	echo '</div>';
echo '</section>';
?>