<?php
/**
 * A bare-bones template file which should load the Narrative scripts and css if opted into, 
 * or load the typical single.php template.
 *
 * @package Narrative
 * @subpackage Narrative
 */


if( !Narrative::template_in_use() ) {
	require_once( get_template_directory() . '/single.php' );
} else {
?>
<!DOCTYPE html>
<!--[if IE 7]>
<html class="ie ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html class="ie ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 7) | !(IE 8) ]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title><?php wp_title( '|', true, 'right' ); ?><?php echo get_bloginfo( 'name' ); ?></title>
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<meta name="generator" content="Narrative <?php echo Narrative_Api::get_instance()->get_version() ?>" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <article id="article" data-content>


	<?php if ( have_posts() ) : ?>

	<?php while ( have_posts() ) : the_post(); ?>

	<?php the_title( '<h1>', '</h1>' );	?>
	<div data-publisher="" ><?php the_author() ?></div>
    <time data-published="" datetime="<?php the_date() ?>"><?php echo get_the_date() ?></time>	
	<?php the_content(); ?>
	
	<?php if ( function_exists( 'vip_powered_wpcom' ) ) : ?>
	<p>
		<small><?php echo vip_powered_wpcom(); ?></small>
	</p>
	<?php endif; ?>

	<?php endwhile; ?>

	<?php endif; ?>
			
	</article>
	<div class="magazine" data-win-control="Controls.FlipView">
		<div data-win-control="UI.HamburgerMenu">
            <a data-win-control="UI.FullScreenButton"></a>
            <a data-win-control="UI.TwitterShare"></a>
            <a data-win-control="UI.FacebookShare"></a>
            <a data-win-control="UI.BackHome" data-win-options="{text: '<?php _e( 'Home', Narrative_Api::get_instance()->get_textdomain() ) ?>'}"></a>
        </div>
        <progress data-win-control="UI.ProgressBar"></progress>
        <div data-win-control="UI.PageNumbers"></div>
	</div>
</body>
</html>

<?php } ?>