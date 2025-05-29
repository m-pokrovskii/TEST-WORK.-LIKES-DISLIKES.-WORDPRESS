<?php

if ( ! defined( 'VERSION' ) ) {
	define( 'VERSION', '1.0.0' );
}

function setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	register_nav_menus(
		array(
			'menu-1' => esc_html__( 'Primary', 'likes' ),
		)
	);

	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);
}

add_action( 'after_setup_theme', 'setup' );

function scripts() {
	wp_enqueue_style( 'style', get_stylesheet_uri(), array(), VERSION );
	wp_style_add_data( 'style', 'rtl', 'replace' );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'scripts' );
add_image_size( 'card-thumbnail', 300, 170, true ); 
add_image_size( 'card-thumbnail-170', 170, 170, true ); 

if (!function_exists('display_thumb')) {
	function display_thumb() { ?>
		<?php if (has_post_thumbnail()) : ?>
		<a class="entry-image-thumb" href="<?php the_permalink(); ?>">
				<?php
					the_post_thumbnail(
						'card-thumbnail',
						array(
							'alt' => the_title_attribute(
								array(
									'echo' => false,
								)
							),
						)
					);
				?>
			</a>
	<?php endif ?>
	<?php }
}