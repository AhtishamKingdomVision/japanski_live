<?php
$bg_color = $section['bg_color'];
$top   	  = $section['padding_top'];
$bottom   = $section['padding_bottom'];

$main_title = $section['main_title'];
$boxes 		= $section['boxes'];

echo '<section class="full-section resorts_boxes" style="background-color: '.$bg_color.';padding-top:'.$top.';padding-bottom: '.$bottom.';">';
	echo '<div class="container">';
		if($main_title){ printf('<h2>%s</h2>', $main_title); }
		if(!empty($boxes)):
			echo '<div class="resorts">';
				foreach ($boxes as $keyi => $value) {
				$image 		= $value['image'];
				$title 		= $value['title'];
				$content 	= $value['content'];
				$option 	= $value['action_option'];
				$pop_btn 	= $value['pop_btn_label'];
				$more 		= $value['more_content'];
				$link 		= $value['link'];
				$features 	= $value['features'];
				$fe_title 	= $value['fe_title'];

					echo '<div class="boxes box_'.$keyi.'">';
						echo '<div class="img" style="background: url('.wp_get_attachment_image_url($image, 'full').')no-repeat center/cover">';
							if($option == 'link'){
								if($link){ 
									$link_url = $link['url'];
									$link_target = $link['target'] ? $link['target'] : '_self';
									printf('<a href="%s" target="%s"></a>', $link_url, $link_target);
								}
							} elseif ($option == 'popup') {
								printf('<a class="" href="javascript:;" data-fancybox data-src="#resorts-content-%s"></a>', $keyi);
							}
						echo '</div>'; //img

						echo '<div class="boxes_con">';
							if($title){ printf('<h3>%s</h3>', $title); }
							echo $content;
							if($option == 'popup'){
								echo '<div class="more_text" style="display: none;" id="resorts-content-'. $keyi .'">';
									//echo $content;
									echo $more;
								echo '</div>';
							}
							if(!empty($features)):
							echo '<hr>';
							if($fe_title){ printf('<strong>%s</strong>', $fe_title); }
								echo '<ul class="feature_list">';
									foreach ($features as $key => $vue) {
									$list = $vue['list'];
										if(!empty($list)){ printf('<li>%s</li>', $list); }
									}
								echo '</ul>';
							endif;
							if($option == 'link'){
								if($link){ 
									$link_url = $link['url'];
									$link_title = $link['title'];
									$link_target = $link['target'] ? $link['target'] : '_self';
									printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title); 
								}
							} elseif ($option == 'popup') {
								printf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#resorts-content-%s">%s</a>', $keyi, $pop_btn);
							}
						echo '</div>'; //boxes_con
					echo '</div>'; //boxes
				}
			echo '</div>'; //resorts
		endif;
	echo '</div>';
echo '</section>';
?>