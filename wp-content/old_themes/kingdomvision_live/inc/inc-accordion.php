<?php
$main_title = $section['main_title'];
$list = $section['list'];

echo '<section class="full-section accordion">';
	echo '<div class="container">';
		echo '<div class="acc__cover">';
			if($main_title){ printf('<h2>%s</h2>', $main_title); }
			if(!empty($list)):
				echo '<div class="acc_list">';
					echo '<ul class="accordion_list">';
						foreach ($list as $key => $value) {
						$question = $value['question'];
						$image 	  = $value['image'];
						$answer   = $value['answer'];
							echo '<li>';
								printf('<div class="question">%s</div>', $question);
								echo '<div class="answer" style="display:none;">';
									if(!empty($image)):
										echo '<div class="acc_image">';
											echo wp_get_attachment_image($image, 'full');
										echo '</div>'; //acc_image
									endif;

									echo '<div class="acc_con">';
										echo $answer;
									echo '</div>'; //acc_con

								echo '</div>'; //answer
							echo '</li>';
						}
					echo '</ul>';
				echo '</div>'; //acc_list
			endif;
		echo '</div>'; //acc__cover
	echo '</div>';
echo '</section>';
?>