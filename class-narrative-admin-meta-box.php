<?php 

/**
 *	Handles creating and saving Narrative admin meta box
 *
 */
class Narrative_Admin_Meta_Box {

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'save_meta_box_data' ) );
		add_action( 'load-post.php', array( __CLASS__, 'post_meta_boxes_setup' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'post_meta_boxes_setup' ) );
	}

	/** 
	 * Create a meta boxes to be displayed on the post editor screen to let the user choose whether to use
	 * Narrative on this post or opt to not.
	 *
	 */
	public static function add_post_meta_boxes() {
		add_meta_box(
			'narrative-templates',          // Unique ID
			esc_html__( 'Storyform templates', Narrative_Api::get_instance()->get_textdomain() ),     // Title
			array( __CLASS__, 'templates_meta_box' ),      // Callback function
			'post',                 // Admin page (or post type)
			'side',                 // Context
			'default'                   // Priority
		);
	}

	/* 
	 * Display the post meta box. 
	 */
	public static function templates_meta_box( $object, $box ) {

		$post_id = get_the_ID();
		$narrative_settings = get_option( 'narrative_settings' );

		// Get application key so we can figure out which templates are supported for this site
		if( $narrative_settings ){
			$app_key = $narrative_settings['narrative_application_key'];    
		} else {
			$app_key = null;
		}

		// Get any previously specified setting
		$options = Narrative_Options::get_instance()->get_options();
		$currentTheme = false;
		foreach( $options as $option ) {
			if( $option['id'] == $post_id && $option['theme'] != 'pthemedefault' ) {
				$currentTheme = $option['theme'];
				break;
			}
		}

		$apiversiondir = Narrative_Api::get_instance()->get_version_directory();

		wp_nonce_field( 'narrative_meta_box', 'narrative_meta_box_nonce' );
		?>

		<p>
			<style type="text/css">
				#narrative-status {
					padding: 0;
					margin: 0;
					color: red;
				}
			</style>
			<label for="narrative-templates"><?php _e( 'Choose which Storyform templates to use for this post.', Narrative_Api::get_instance()->get_textdomain() ); ?></label>
			<p id="narrative-status">This Storyform plugin is now out-dated. Please deactivate this one and install the <a href="https://wordpress.org/plugins/storyform/">new one</a>. Your settings will be preserved.</p>
			<br />
			<select class="widefat" name="narrative-templates" id="narrative-templates-select" >
				<option id="narrative-default-theme" value="pthemedefault">[Do not use Storyform]</option>
				<option id="narrative-templates-loading" value="loading" disabled="true">Loading other themes...</option>
			</select>
			<script>
				function xhr(options, cb){
					var req = new XMLHttpRequest();
					req.onreadystatechange = function () {
						if (req.readyState === 4) {
							if (req.status >= 200 && req.status < 300) {
								cb(req);
							} else {
								cb(null, req);
							}
							req.onreadystatechange = function () { };
						} else {
							cb(null, null, req);
						}
					};

					req.open('GET', options.url, false);
					req.send();

				}

				var el = document.getElementById('narrative-templates-select');
				var loading = document.getElementById('narrative-templates-loading');

				<?php if($currentTheme) { ?>

				var option = document.createElement('option');
				option.value = '<?php echo $currentTheme ?>';
				option.textContent = '<?php echo $currentTheme ?>';
				option.setAttribute('selected', true);
				el.appendChild(option);
				el.appendChild(loading); // Move loading to the end

				<?php } ?>

				el.addEventListener('focus', function clickSelect(){
					xhr({url: '<?php echo $apiversiondir ?>data/templategroups?app_key=<?php echo $app_key ?>'}, function(response, err){
						if(response){
							loading.parentNode.removeChild(loading);
							var templates = JSON.parse(response.responseText);
							templates.forEach(function(template){
								if(template.id !== '<?php echo $currentTheme ?>'){
									var option = document.createElement('option');
									option.value = template.id;
									option.textContent = template.id;
									el.appendChild(option);
								}
								
							});
						} else if(err){
							document.getElementById('narrative-status').textContent = 'Cannot retrieve templates. Ensure Settings > Storyform Settings > Application key is set correctly.';

						}
					});
					el.removeEventListener('focus', clickSelect, false);
				}, false);
				
			</script>

		</p>

	<?php }

	/* 
	 * Meta box setup function. 
	 * Fire our meta box setup function on the post editor screen.
	 */
	public static function post_meta_boxes_setup() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_post_meta_boxes' ) );
	}

	/**
	 * When the post is saved, saves our Narrative template choice.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public static function save_meta_box_data( $post_id ) {

		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['narrative_meta_box_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['narrative_meta_box_nonce'], 'narrative_meta_box' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// If this is a revision, don't do anything
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}

		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		/* OK, its safe for us to save the data now. */
		
		// Make sure that it is set.
		if ( ! isset( $_POST['narrative-templates'] ) ) {
			return;
		}

		// Sanitize user input.
		$data = sanitize_text_field( $_POST['narrative-templates'] );

		// Create object to save
		$post = array();
		$post['id']  = intval( $post_id );
		$post['url'] = sanitize_text_field( strtolower( $_POST['post_name'] ) );
		$post['theme'] = $data; // sanitized above.

		// Get and update current options
		$options = Narrative_Options::get_instance()->get_options();
		$newOptions = array();
		foreach( $options as $option ) {
			if( $option['id'] == $post_id ) {
				continue;
			}
			array_push( $newOptions, $option );
		}
		if( 'pthemedefault' != $data ) {
			array_push( $newOptions, $post ); // add new option for this post
		}
		Narrative_Options::get_instance()->set_options( $newOptions );

	}
}
