<?php
$top 	= $section['padding_top'];
$bottom = $section['padding_bottom'];
$color  = $section['bg_color'];

$main_title = $section['main_title'];
$content 	= $section['content'];
$columns 	= $section['columns'];
$boxes 		= $section['boxes'];

echo '<section class="full-section featured_properties" style="background: '.$color.'; padding-top: '.$top.'; padding-bottom: '.$bottom.';">';
	echo '<div class="container">';
		if($main_title){ printf('<h2>%s</h2>', $main_title); }
		if($content){ printf('<div class="mini_con">%s</div>', $content); }
		echo '<div class="fboxes '.$columns.'">';
			echo '<ul class="fboxes_inn">';
				if($boxes):
					foreach ($boxes as $key => $value) {
					$image = $value['image'];
					$title = $value['title'];
					$link  = $value['link'];
					$desc = $value['short_desc'];
					// $img_url = wp_get_attachment_image_url($image , 'full');

							echo '<li class="inn_fboxes">';
								if($link) {
							    $link_url = $link['url'];
							    $link_target = $link['target'] ? $link['target'] : '_self';

									echo '<a href="'.$link_url.'" target="'.$link_target.'">';
										echo wp_get_attachment_image($image , 'full');
										if($title){ printf('<h3>%s</h3>', $title); }
									echo '</a>';
									echo '<div class="fp_short">'.$desc.'</div>';

								} else {
									echo wp_get_attachment_image($image , 'full');
									if($title){ printf('<h3>%s</h3>', $title); }
									echo '<div class="fp_short">'.$desc.'</div>';
								}
							echo '</li>';
					}
				endif;
			echo '</ul>'; //fboxes_inn
		echo '</div>'; //fboxes
	echo '</div>';
echo '</section>';
?>