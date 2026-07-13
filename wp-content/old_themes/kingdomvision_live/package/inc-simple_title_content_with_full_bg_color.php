<?php
$bg_color = $section['bg_color'];
$title 	  = $section['title'];
$link     = $section['link'];
$class    = $section['class'];
$content  = $section['content'];
$top   	  = $section['padding_top'];
$bottom   = $section['padding_bottom'];
$options  = $section['action_option'];
$pop_btn  = $section['pop_btn_label'];
$more     = $section['more_content'];

if($bg_color == '#ffffff' || $bg_color == ''){
	$text = "black_text";
} else {
	$text = "default";
}
echo '<section class="'. $class .' full-section simple_title_content_with_full_bg_color '. $text .'" style="background-color: '.$bg_color.';padding-top:'.$top.';padding-bottom: '.$bottom.';">';
	echo '<div class="container">';
		echo '<div class="stcwfbc">';
			if(!empty($title)){ printf('<h2>%s</h2>', $title); }
			echo $content;
			if($options == 'popup'){
				echo '<div class="more_text" style="display: none;" id="stcwfbc-content">';
					//echo $content;
					echo $more;
				echo '</div>';
			}
			if($options == 'link'){
				if($link){
				$link_url = $link['url'];
				$link_title = $link['title'];
				$link_target = $link['target'] ? $link['target'] : '_self';
					printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title); 
				}
			} elseif ($options == 'popup') {
				printf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#stcwfbc-content">%s</a>', $pop_btn);
			}
		echo '</div>'; //stcwfbc
	echo '</div>';
echo '</section>';
?>
<style type="text/css">
	section.full-section.simple_title_content_with_full_bg_color.fixed-top:before { background: <?php echo $bg_color; ?>;}
</style>