<?php /*

**************************************************************************

Plugin Name:  Storyform (outdated)
Plugin URI:   http://www.narrativetale.com/wordpress-plugin
Version:      0.3.7
Description:  This plugin is now deprecated. Please install the new plugin at https://wordpress.org/plugins/storyform/.
Author:       Storyform
Author URI:   http://storyform.co

**************************************************************************/

require_once( dirname( __FILE__ ) . '/config.php');
require_once( dirname( __FILE__ ) . '/editor/narrative-editor.php' );
require_once( dirname( __FILE__ ) . '/media/narrative-media.php' );
require_once( dirname( __FILE__ ) . '/class-narrative-options.php');
require_once( dirname( __FILE__ ) . '/class-narrative.php');
require_once( dirname( __FILE__ ) . '/class-narrative-settings-page.php');
require_once( dirname( __FILE__ ) . '/class-narrative-admin-meta-box.php');
require_once( dirname( __FILE__ ) . '/user-agents.php');

$narrative = Narrative::get_instance()->init();

if( is_admin() ) {
	$narrative_settings_page = new Narrative_Settings_Page();
}

function narrative_init() {
	load_plugin_textdomain( Narrative_Api::get_instance()->get_textdomain(), false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'narrative_init' ); 

Narrative_Admin_Meta_Box::init();


/**
 * Narrative theme setup.
 *
 * Does some basic theme stuff like establishing support for RSS feed links and hiding the admin bar
 *
 */
if ( ! function_exists( 'narrative_setup' ) ) :
function narrative_setup() {
	if( Narrative::template_in_use() ) {
		add_theme_support( 'automatic-feed-links' ); // Add RSS feed links to <head> for posts and comments.
		show_admin_bar( false );  
	}

}
endif; // narrative_setup
add_action( 'after_setup_theme', 'narrative_setup' );


?>
