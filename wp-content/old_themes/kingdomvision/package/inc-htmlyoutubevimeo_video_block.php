<?php
$top 	= $section['padding_top'];
$bottom = $section['padding_bottom'];

$video_selection  = $section['video_selection'];

$youtube_video_id = $section['youtube_video_id'];
$vimeo_video_id 	= $section['vimeo_video_id'];
$html5_video 	= $section['html5_video'];

$block_id 		= $section['block_id'];
$block_class 		= $section['block_class'];

echo '<section class="full-section htmlyoutubevimeo_video '.$block_class.'"  style="padding-top: '.$top.'; padding-bottom: '.$bottom.';">';
	echo '<div class="container">';
		if( $video_selection == 'youtube' ){
			echo '<div class="iframe-wrapper">';
				echo '<iframe src="https://www.youtube.com/embed/'.$youtube_video_id.'" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; allowfullscreen"></iframe>';
			echo '</div>'; #iframe-wrapper
		}else if( $video_selection == 'vimeo' ) {
			echo '<div class="iframe-wrapper">';
				echo '<iframe src="https://player.vimeo.com/video/'.$vimeo_video_id.'" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen"></iframe>';
			echo '</div>'; #iframe-wrapper
		}else{
			echo '<div class="video-wrapper">';
				echo '<video controls><source src="'.$html5_video.'" type="video/mp4"></video>';
			echo '</div>'; #video-wrapper
		}
	echo '</div>'; #container
echo '</section>'; #htmlyoutubevimeo_video
?>