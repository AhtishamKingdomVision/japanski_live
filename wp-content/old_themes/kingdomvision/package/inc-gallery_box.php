<?php  

$title 		= $section['title'];
$sub_title 	= $section['sub_title'];
$boxes 		= $section['boxes'];
$sec_ID 	= $section['section_id'];

echo '<section class="full-section gallery-box" '.($sec_ID ? 'id="'.$sec_ID.'"' : '').'>';
	echo '<div class="container">';
		if($title){
			echo '<h2>'.$title.'</h2>';
		}
		if($sub_title){
			echo '<p>'.$sub_title.'</p>';
		}
		if(!empty($boxes)){
			echo '<ul>';
				foreach ($boxes as $key => $box) {
					$img = $box['full_image'];
					echo '<li>';
						echo '<a data-fancybox="trigger-element-gallery" href="'.$img['url'].'" >';
							echo '<img src="'.$img['url'].'" alt="'.$img['title'].'">';
							echo '<span>Enlarge Image</span>';
						echo '</a>';
					echo '</li>';
				}
			echo '</ul>';
		}
	echo '</div>';
echo '</section>';

?>