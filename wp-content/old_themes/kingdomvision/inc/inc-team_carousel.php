<?php
$main_title = $section['main_title'];
$content = $section['content'];
$option = $section['layout_option'];
$team = $section['team'];

echo '<section class="full-section team_carousel '.($option == 'boxes' ? 'updated' : '').'" >';
	echo '<div class="container">';
		echo '<div class="tc__inn">';
			if($main_title){ printf('<h2>%s</h2>', $main_title); }
			if($content){ printf('<div class="tc_con">%s</div>', $content); }
			if(!empty($team)):
				echo '<div class="'.($option == 'carousel' ? 'tc_carousel' : 'tc_boxes').' ">';
					foreach ($team as $key => $value) {
					$image = $value['image'];
					$title = $value['title'];
					$designation = $value['designation'];
					$content = $value['content'];

						echo '<div class="tc_list">';
							
							echo '<div class="tc_conn">';
								echo $content;
							echo '</div>'; //tc_conn
						
							echo '<div class="tc_img">';
								echo '<div class="left">';
									echo wp_get_attachment_image($image, 'full');
								echo '</div>';
								echo '<div class="right">';
									if($title){ printf('<h3>%s</h3>', $title); }
									if($designation){ printf('<h4>%s</h4>', $designation); }
								echo '</div>';
							echo '</div>'; //tc_img

						echo '</div>'; //tc_list
					}
				echo '</div>'; //tc_carousel
			endif;
		echo '</div>'; //tc__inn
	echo '</div>';
echo '</section>';

?>