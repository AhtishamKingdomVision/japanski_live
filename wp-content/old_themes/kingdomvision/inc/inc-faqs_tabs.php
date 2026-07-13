<?php
$main_title = $section['main_title'];
$tabs 		= $section['tabs'];

echo '<section class="full-section tabs kv_custom">';
	echo '<div class="container">';
		if($main_title){ printf('<h2>%s</h2>', $main_title); }
		echo '<div class="tab-container">';
			echo '<div class="tab-menu">';
				echo '<ul>';
					if($tabs):
						foreach ($tabs as $key => $value) {
						$title = $value['title'];
							printf('<li><a href="javascript:;" class="tab-a active-a" data-id="tab%s">%s</a></li>', $key, $title);
						}
					endif;
				echo '</ul>';

				if($tabs):
					foreach ($tabs as $key => $value) {
					$data = $value['data'];
						printf('<div class="tab" data-id="tab%s">', $key);
							if(!empty($data)){
								echo '<section class="accordion kv_custom">';
									echo '<ul class="accordion_list">';
										foreach ($data as $key => $inn) {
										$question = $inn['question'];
										$answer   = $inn['answer'];
											echo '<li>';
												printf('<div class="question">%s</div>', $question);
												echo '<div class="answer" style="display:none;">';
													echo '<div class="acc_con">';
														echo $answer;
													echo '</div>'; //acc_con
												echo '</div>'; //answer
											echo '</li>';
										}
									echo '</ul>';	
								echo '</section>';
							}
						echo '</div>'; //tab
					}
				endif;
			echo '</div>'; //tab-menu
		echo '</div>';  //tab-container
	echo '</div>';
echo '</section>';
?>
<script>
jQuery(document).ready(function(){ 
	jQuery(".tab-a").removeClass('active-a');

	jQuery(".tab-a").first().addClass('active-a');
	jQuery(".tab").first().addClass('tab-active');

    jQuery('.tab-a').on('click' , function(){  
      jQuery(".tab").removeClass('tab-active');
      jQuery(".tab[data-id='"+jQuery(this).attr('data-id')+"']").addClass("tab-active");
      jQuery(".tab-a").removeClass('active-a');
      jQuery(this).parent().find(".tab-a").addClass('active-a');
     });
});
</script>