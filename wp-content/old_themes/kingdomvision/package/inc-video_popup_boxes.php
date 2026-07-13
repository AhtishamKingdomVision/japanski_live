<?php
$main_title = $section['main_title'];
$bg_color = $section['bg_color'];
$videos_boxes = $section['videos_boxes'];

echo '<section class="full-section video_popup_boxes" style="background: '.$bg_color.';">';
	echo '<div class="container">';
		if($main_title){ printf('<h2>%s</h2>', $main_title); }
		echo '<div class="vpb_box">';
			if(!empty($videos_boxes)):
				foreach ($videos_boxes as $key => $value) {
				$image 		= $value['image'];
				$video_link = $value['video_link'];
					echo '<div class="video_list" style="background: url('.$image.') no-repeat center/cover;">';
						echo '<a class="vpb_fancybox" data-fancybox href="'.$video_link.'"></a>';
					echo '</div>'; //video_list
				}
			endif;
		echo '</div>'; //vpb_box
	echo '</div>';
echo '</section>';
?>