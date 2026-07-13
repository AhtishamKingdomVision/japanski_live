<?php
$boxes = $section['boxes'];
echo '<section class="full-section four_resort_boxes">';
	echo '<div class="container">';
		if(!empty($boxes)):
			echo '<div class="frb_inn">';
				foreach ($boxes as $key => $value) {
				$image   = $value['image'];
				$title 	 = $value['title'];
				$link 	 = $value['link'];
				$content = $value['content'];

					echo '<div class="frb_list" >';
						if($link){
						$link_url = $link['url'];
						$link_target = $link['target'] ? $link['target'] : '_self';
							echo '<a href="'.($link_url == '#' ? 'javascript:;' : $link_url).'" target="'.$link_target.'">';
								if(!empty($title)){ 
									echo '<div class="title-box">';
										printf('<h3>%s</h3>', $title); 
									echo '</div>';
								}
								echo '<div class="frb_con" style="background: url('.$image.') no-repeat center/cover;">';
									echo '<div class="inner-cont">';
										echo $content;
									echo '</div>';
								echo '</div>'; //frb_con
							echo '</a>';
						} else {
							if(!empty($title)){ printf('<h3>%s</h3>', $title); }
							echo '<div class="frb_con">';
								echo $content;
							echo '</div>'; //frb_con
						}
					echo '</div>';
				}
			echo '</div>'; //frb_inn
		endif;
	echo '</div>';
echo '</section>';
?>