<?php
/**
 * Singleton that is responsible for storing the data specifying which posts should use Narrative and which 
 * Narrative templates to use. We use the get/set_options mechanism since it is loaded early enough. 
 *
 */
class Narrative_Options {

	protected static $instance = false;

	public static function get_instance() {
		if( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Gets the options array
	 *
	 */
	function get_options() {
		// Load page option settings
		// Some versions of Wordpress deserialize automatically. WP0.3 doesn't.
		try {
			$options = unserialize( get_option( 'narrative_theme' ) );
		} catch(Exception $e) {
			$options = get_option( 'narrative_theme' );
		}
		
		if ( gettype($options) != 'array' ) {
			$options = array();
		}

		return $options;
	}

	/**
	 * Sets the options array
	 *
	 */
	function set_options( $options ) {
		update_option( 'narrative_theme', serialize( $options ) );
	}

}