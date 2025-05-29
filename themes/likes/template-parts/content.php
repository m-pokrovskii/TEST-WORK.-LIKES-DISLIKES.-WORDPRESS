<article id="post-<?php the_ID(); ?>" <?php post_class('likes-card'); ?>>
	<div class="entry-image likes-card__image">
		<?php display_thumb() ?>
	</div>
	<div class="likes-card__content">
		<header class="entry-header">
		<h2 class="entry-title">
			<a href="<?php echo esc_url( get_permalink() ) ?>">
				<?php echo get_the_title(); ?>
			</a>
		</h2>
		</header>
		<div class="entry-content">
			<?php
				the_content();
			?>
		</div>
		<div class="entry-meta">
			<div class="entry-meta__author">
				<?php
					echo esc_html__( 'Author:', 'likes' )
				?>
				<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) ?>">
					<?php echo esc_html( get_the_author() ) ?>
				</a>
			</div>
			<div class="entry-meta__like-buttons">
				<?php echo likes_buttons(get_the_ID()) ?>
			</div>
		</div>				
	</div>
</article><!-- #post-<?php the_ID(); ?> -->