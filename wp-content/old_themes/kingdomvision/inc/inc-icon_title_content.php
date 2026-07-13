<?php
$title = $section['main_title'];
$boxes = $section['boxes'];

echo '<section class="full-section icon_title_content">';
	echo '<div class="container">';
		if($title){ printf('<h2>%s</h2>', $title); }
		if(!empty($boxes)):
			echo '<ul>';
				foreach ($boxes as $key => $value) {
				$image   = $value['image'];
				$title   = $value['title'];
				$content = $value['content'];

				echo '<li>';
					echo wp_get_attachment_image($image, 'full');
					if($title){ printf('<h3>%s</h3>', $title); }
					echo $content;
				echo '</li>';
				}
			echo '</ul>';
		endif;
	echo '</div>';
echo '</section>';
?>