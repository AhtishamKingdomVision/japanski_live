<?php
$bg_image = $section['bg_image'];
$overlay  = $section['overlay'];
$title 	  = $section['title'];
$form_id  = $section['form_id'];
$content  = $section['content'];
if($overlay == true){
	$over = 'overlay';
} else {
	$over = '';
}

if(!empty($bg_image)){
	echo '<section class="full-section newsletter_section '.$over.'" style="background: url('.$bg_image.') no-repeat center/cover;">';
} else {
	echo '<section class="full-section newsletter_section without_bg">';
}
	echo '<div class="container">';
		echo '<div class="ns_inn">';
			if($title){ printf('<h2>%s</h2>', $title); }
			echo do_shortcode('[gravityform id="'.$form_id.'" title="false" description="false" ajax="true"]');
			if($content){ printf('<div class="f_cont">%s</div>', $content); }
		echo '</div>'; //ns_inn
	echo '</div>';
echo '</section>';
?>