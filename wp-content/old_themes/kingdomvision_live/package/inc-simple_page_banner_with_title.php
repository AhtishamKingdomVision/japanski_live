<?php
$image   = $section['image'];
$title   = $section['title'];
$tag     = $section['title_tag'];
$content = $section['content'];

echo '<section class="full-section simple_page_banner_with_title" style="background: url('.$image.') no-repeat center/cover;">';
	echo '<div class="container">';
		if($title){ printf('<%s>%s</%s>', $tag, $title, $tag); }
		if($content){ printf('<div class="ttdl_con">%s</div>', $content); }
	echo '</div>';
echo '</section>';
?>