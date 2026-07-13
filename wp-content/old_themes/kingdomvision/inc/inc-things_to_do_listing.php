<?php
$main_title = $section['main_title'];
$content = $section['content'];
$lists = $section['lists'];

echo '<section class="full-section things_to_do_listing">';
	echo '<div class="container">';
		if($main_title){ printf('<h2>%s</h2>', $main_title); }
		if($content){ printf('<div class="ttdl_con">%s</div>', $content); }
		if(!empty($lists)):
			echo '<div class="ttdl_list">';
				echo '<ul>';
					foreach ($lists as $key => $value) {
					$image 		= $value['image'];
					$title 		= $value['title'];
					$options	= $value['action'];
					$link 		= $value['link'];
					$pop_btn	= $value['popup_btn_label'];
					$content 	= $value['content'];
					$more_con 	= $value['more_content'];

						echo '<li>';
							echo wp_get_attachment_image($image, 'full');
							echo '<div class="ttdl__inn">';
								if($title){ printf('<h3>%s</h3>', $title); }
								echo $content;
						if($options == 'popup'){
							echo '<div class="more_text" style="display: none;" id="ttdl-content-'.$key.'">';
								echo $more_con;
							echo '</div>';
						}
							if($options == 'link'){
								if($link){
									$link_url = $link['url'];
									$link_title = $link['title'];
									$link_target = $link['target'] ? $link['target'] : '_self';
									printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title); 
								}
							} elseif ($options == 'popup') {
								echo sprintf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#ttdl-content-'.$key.'">%s</a>', $pop_btn);
							}
							echo '</div>'; //ttdl__inn
						echo '</li>';
					}
				echo '</ul>';
			echo '</div>'; //ttdl_list
		endif;

	echo '</div>';
echo '</section>';
?>