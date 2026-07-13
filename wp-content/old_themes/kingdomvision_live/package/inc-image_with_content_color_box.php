<?php
$main_title = $section['main_title'];
$listing 	= $section['listing'];
echo '<section class="full-section image_with_content_color_box">';
	echo '<div class="container">';
		if(!empty($main_title)){ printf('<h2>%s</h2>', $main_title); }
			if(!empty($listing)):
				foreach ($listing as $key => $value) {
				$align   = $value['image_aligment'];
				$image   = $value['image'];
				$title   = $value['title'];
				$options = $value['action'];
				$link    = $value['link'];
				$pop_btn = $value['pop_btn_label'];
				$popup_id = $value['popup_id'];
				$content = $value['content'];
				$more_con = $value['more_content'];

				echo '<div class="iwccb '.$align.'">';
					echo '<div class="iwccb_img" style="background:url('.$image.') no-repeat center/cover;"></div>'; //iwccb_img
					echo '<div class="iwccb__con">';
						if(!empty($title)){ printf('<h3>%s</h3>', $title); }
						echo $content;
						if($options == 'popup'){
							echo '<div class="more_text" style="display: none;" id="'.$popup_id.'">';
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
							echo sprintf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#'.$popup_id.'">%s</a>', $pop_btn);
						}
					echo '</div>'; //iwccb__con
				echo '</div>'; //iwccb
				}
			endif;
	echo '</div>';
echo '</section>';
?>