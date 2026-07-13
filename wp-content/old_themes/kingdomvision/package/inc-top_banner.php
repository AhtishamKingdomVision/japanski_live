<?php
$image = $section['image'];
$title = $section['title'];
$tag   = $section['tag'];
$id    = $section['form_id'];
$display = $section['display_form'];
$text  = $section['more_text'];

echo '<section class="full-section top_banner" style="background: url('.$image.') no-repeat center/cover;">';
	echo '<div class="container">';
		if($title){ printf('<%s>%s</%s>', $tag, $title, $tag); }
	echo '</div>';
	echo '<div class="enquire_form">';
			if($text):
				echo '<a class="btn light_green fm_open" href="javascript:;">Learn More</a>';
			endif;
			if($display == true):
				echo do_shortcode('[gravityform id="'.$id.'" title="false" description="false" ajax="true"]');
			endif;
			if($text):
				echo '<div class="more_text" style="display:none;">';
					echo '<a class="fm_close" href="javascript:;"><i class="fa fa-close"></i></a>';
					echo $text;
				echo '</div>'; //more_text
			endif;
	echo '</div>'; //enquire_form
echo '</section>';
?>