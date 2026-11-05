<?php /* Template Name: Blank Template */ ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0"/>
	<style>body{background:transparent}</style>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div>
	<?php
		if ( have_posts() ) {
			the_post();
			the_content();
		}
	?>
</div>
<?php wp_footer(); ?>
</body>
</html>