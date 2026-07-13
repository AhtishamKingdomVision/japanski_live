<?php 
/**
 * 404 ( Not fount page )
 */
get_header();?>

	<div class="content-wrapper">
		<section class="full-section gdl-page-404">
			<div class="container">
				<div class="message-box-wrapper">
					<h2>Sorry This Page Does Not Exist!</h2>
					<div class="message-box-title">
						<div class="cover404">
							<span>4</span>
							<span>0</span>
							<span>4</span>						
						</div>
					</div>
					<div class="message-box-content">
					You are half way around the world, but oops you make the wrong trun,<br>
					lets us lead to the right way, Return to our <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home Page</a>
					</div>
				</div>
			</div>
			<div class="clear"></div>
		</section>
	</div>
<?php get_footer();?>