<?php
$sec_ID 	= $section['section_id'];
$title 		= $section['title'];
$content 	= $section['content'];

echo '<section class="full-section simple_text" '.( $sec_ID ? 'id="'.$sec_ID.'"' : '' ).'>';
	echo '<div class="container">';
		if($title){ printf('<h2>%s</h2>', $title); }
		echo $content;
	echo '</div>';
echo '</section>';
?>