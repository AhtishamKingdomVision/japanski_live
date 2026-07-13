<?php 

$title 		= $section['title'];
$listings 	= $section['listings'];
$sec_ID 	= $section['section_id'];

echo '<section class="full-section facilities-sec" '.($sec_ID ? 'id="'.$sec_ID.'"' : '').'>';
	echo '<div class="container">';
		if($title){
			echo '<h2>'.$title.'</h2>';
		}
		if($listings){
			echo '<ul>';
				foreach ($listings as $key => $list) {
					$icon = $list['icon'];
					$label = $list['label'];
					
					echo '<li>';
						if($icon){
							echo '<img src="'.$icon['url'].'" alt="'.$icon['title'].'"/>';
						}
						echo $label;
					echo '</li>';

				}
			echo '</ul>';
		}
	echo '</div>';
echo '</section>';

?>