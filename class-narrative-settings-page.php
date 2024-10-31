<?php
/**
 * Handles setting up the Admin Settings > Narrative Settings page, which takes an application key.
 *
 */
class Narrative_Settings_Page
{
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page()
	{
		// This page will be under "Settings"
		add_options_page(
			'Settings Admin',
			'Storyform Settings',
			'manage_options',
			'narrative-setting-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page()
	{
		// Set class property
		$this->options = get_option( 'narrative_settings' );
		?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Storyform Settings</h2>
		<form method="post" action="options.php">
			<?php
			// This prints out all hidden setting fields
			settings_fields( 'narrative_option_group' );
			do_settings_sections( 'narrative-setting-admin' );
			submit_button();
			?>
		</form>
	</div>
	<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init()
	{
		register_setting(
			'narrative_option_group', // Option group
			'narrative_settings', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'narrative_section_id', // ID
			'Application Settings', // Title
			array( $this, 'print_section_info' ), // Callback
			'narrative-setting-admin' // Page
		);

		add_settings_field(
			'narrative_application_key', // ID
			'Storyform application key', // Title
			array( $this, 'narrative_application_key_callback' ), // Callback
			'narrative-setting-admin', // Page
			'narrative_section_id' // Section
		);

	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input )
	{
		$new_input = array();
		if( isset( $input['narrative_application_key'] ) ) {
			$new_input['narrative_application_key'] =  sanitize_text_field( $input['narrative_application_key'] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info()
	{
		print 'Enter your Storyform application key:';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function narrative_application_key_callback()
	{
		printf(
			'<input type="text" id="narrative_application_key" name="narrative_settings[narrative_application_key]" value="%s" />',
			isset( $this->options['narrative_application_key'] ) ? esc_attr( $this->options['narrative_application_key']) : ''
		);
	}

}