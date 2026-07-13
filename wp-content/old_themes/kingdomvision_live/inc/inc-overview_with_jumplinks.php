<?php  
$title 		= $section['title'];
$sub_title 	= $section['sub_title'];
$cont 		= $section['content'];

$options	= $section['options'];
$link 		= $section['link'];
$pop_btn	= $section['pop_btn'];
$more 		= $section['more_content'];
$jumps 		= $section['jumplink'];

echo '<section id="jump" class="full-section overview_with_jumplinks">';
	echo '<div class="container">';
		if($title){
			echo '<h2>'.$title.'</h2>';
		}
		if($sub_title){
			echo '<h4>'.$sub_title.'</h4>';
		}
		if($cont){
			echo '<div class="cont">';
				echo $cont;
				if($options == 'popup'){
					echo '<div class="more_text" style="display: none;" id="overview-c">';
						echo $more;
					echo '</div>';
				}
				echo '<div class="center_btn">';
					if($options == 'link'){
						if($link){
						$link_url = $link['url'];
						$link_title = $link['title'];
						$link_target = $link['target'] ? $link['target'] : '_self';
							printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title); 
						}
					} elseif ($options == 'popup') {
						printf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#overview-c">%s</a>', $pop_btn);
					}
				echo '</div>'; //center_btn
			echo '</div>';
		}
	echo '</div>';
	$jumper =  count($jumps) > 5 ? 'jumper' : '';
	if(!empty($jumps)){
		echo '<div class="jumplinks '.$jumper.'">';
			echo '<div class="container">';
				echo '<ul>';
					foreach ($jumps as $key => $value) {
						$label = $value['lable'];
						$ID = $value['section_id'];

						echo '<li><a href="#'.$ID.'">'.$label.'</a></li>';
					}
				echo '</ul>';
			echo '</div>';
		echo '</div>';
	}

echo '</section>';
?>