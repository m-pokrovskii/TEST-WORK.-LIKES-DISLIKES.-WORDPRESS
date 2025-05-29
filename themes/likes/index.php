<?php
	get_header();
?>
	<div class="main-area">
		<div class="site-main">
			<?php
			if ( have_posts() ) :

				if ( is_home() && ! is_front_page() ) :?>
					<header class="main-header">
						<h1 class="page-title screen-reader-text">
							<?php single_post_title(); ?>
						</h1>
					</header>
				<?php endif; ?>

				<div class="cards-list">
					<h2 class="cards-list__headline">Articles</h2>
					<?php 
						while ( have_posts() ) :
							the_post();
							get_template_part( 'template-parts/content', get_post_type() );

						endwhile;

						the_posts_pagination();

					else :

						get_template_part( 'template-parts/content', 'none' );

					endif; ?>
				</div>
		</div>
		<?php get_sidebar(); ?>
	</div>

<?php
	get_footer();
?>