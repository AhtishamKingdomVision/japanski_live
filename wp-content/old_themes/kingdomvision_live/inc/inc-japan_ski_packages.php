<?php
$main_title = $section['main_title'];
$content    = $section['content'];
$packages   = $section['packages'];

echo '<section class="full-section japan_ski_packages">';
	echo '<div class="container">';
		echo '<div class="jsp_packages">';
		if($main_title){ printf('<h2>%s</h2>', $main_title); }
		if($content){ printf('<div class="jsp_con">%s</div>', $content); }
		if(!empty($packages)):
			echo '<div class="jsp_list">';
				echo '<ul>';
					foreach ($packages as $key => $value) {
					$image     = $value['image'];
					$title     = $value['title'];
					$date      = $value['date'];
					$sub_title = $value['sub_title'];
					$jpy       = $value['jpy'];
					$content   = $value['content'];
					$buttons   = $value['buttons'];

						echo '<li>';
							echo '<div class="jsp_list_left">';
								if($title){ printf('<h3>%s <span>%s</span></h3>', $title, $date); }
								if($sub_title){ printf('<h4>%s</h4>', $sub_title); }
								echo $content;
								if($jpy){ printf('<h5>%s</h5>', $jpy); }
							echo '</div>'; //jsp_list_left

							echo '<div class="jsp_list_right">';
								echo wp_get_attachment_image($image, 'full');
								if($buttons):
									echo '<div class="pack_btn">';
										foreach ($buttons as $key => $val) {
										if(empty($val))
											continue;
										$link = $val['link'];
										if(empty($link['url'] ) && empty( $link['title'] ) )
											continue;
											// pre( $link['url'] );
											$link_url 	= $link['url'];
											$link_title = $link['title'];
											$link_targe = $link['target'] ? $link['target'] : '_self';
												printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_targe, $link_title);
										}
									echo '</div>';
								endif;
							echo '</div>'; //jsp_list_right
						echo '</li>';
					}
				echo '</ul>';
			echo '</div>';
		endif;
		echo '</div>'; //jsp_packages
	echo '</div>';
echo '</section>';
?>