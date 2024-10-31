<?php

/*
 * Add plugin to TinyMCE Editor
 */
add_filter( "mce_external_plugins", "narrative_add_buttons" );
function narrative_add_buttons( $plugin_array ) {
    $plugin_array['narrative'] = plugin_dir_url( __FILE__ ) . 'narrative-plugin.js';
    return $plugin_array;
}

/*
 * Add pullquote button
 */
add_filter( 'mce_buttons', 'narrative_register_buttons' );
function narrative_register_buttons( $buttons ) {
    array_push( $buttons, 'pullquote' );
    return $buttons;
}

/*
 * Make sure there is a visual indication of the pullquote.
 */
function narrative_add_editor_styles() {
    add_editor_style( plugin_dir_url( __FILE__ ) . 'narrative-editor-style.css' );
}
add_action( 'after_setup_theme', 'narrative_add_editor_styles' );

/*
 * Add pullquote button to "Text" view within WordPress.
 */
add_action( 'admin_print_footer_scripts', 'narrative_quicktags' );
function narrative_quicktags () {
	if ( wp_script_is( 'quicktags' ) ) {
	?>
		<script type="text/javascript">
			QTags.addButton( 'narrative_pullquote', 'pullquote', '<span class="pullquote">', '</span>', 'p', 'Pullquote', 200 );
		</script>
	<?php
	}
}



?>