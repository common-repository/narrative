<?php
/**
 *
 * This class handles actually switching the theme over to a fake one in order to avoid loading functions.php of 
 * the current one (except for WordPress VIP where this doesn't matter since the theme is already loaded). This class
 * also load the appropriate template and enqueues the scripts. 
 * 
 */
class Narrative {

	protected static $instance;
	private $supported_browser;
	private $options;
	private $narrative_template;

	/**
	 * 	Get the Singleton instance.
	 */
	public static function get_instance() {
		if( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/*
	 *	If this is a supported browser, returns the template in use, or null.
	 */
	public static function template_in_use() {
		$narrative = Narrative::get_instance();
		if( $narrative->is_supported_browser() ) {
			return $narrative->get_narrative_template();	
		}
		return null;
	}

	/**
	 * 	Basic constructor which grabs the Narrative data and checks the browser.
	 */
	private function __construct() {
		$this->supported_browser = narrative_check_user_agent( $_SERVER['HTTP_USER_AGENT'] );
		$this->options = Narrative_Options::get_instance()->get_options();
	}

	/*
	 * Sets up the necessary actions and filters. 
	 */
	public function init(){
		add_action( 'setup_theme', array( &$this, 'setup_theme' ) ); 	
		add_filter( 'template_include', array( &$this, 'template_include' ), 9999 );
	}

	/**
	 * Prior to setting up the theme we must determine the post ID or name and switch themes if specified.
	 * We must parse the request ourselves since WordPress isn't fully running or requires repetitive DB requests.
	 *
	 */
	function setup_theme(){
		// Try to avoid loading the theme on non-VIP. VIP Quickstart requires we check this function not global.
		if( ! function_exists( 'wpcom_vip_load_plugin' ) ) {
			$ruri = $_SERVER['REQUEST_URI'];

			$post = $this->narrative_url_to_post( $ruri );
			if( $post ) {
				$this->set_theme_if_post_matches( $post );
			}
		}
	}

	/**
	 * Examine a url and try to determine the post ID or post name it represents.
	 *
	 * Adapted from url_to_postid, but we attempt to have no DB requests by ignoring page matches, looking
	 * only at posts and not looking up the post ID when we have the post name we can use.
	 *
	 *
	 * @param string $url Permalink to check.
	 * @return int|string Post ID or Post Name or 0 on failure.
	 */
	function narrative_url_to_post( $url ) {
		global $wp_rewrite;

		// First, check to see if there is a 'p=N' or 'page_id=N' to match against
		if ( preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values) )   {
			$id = absint($values[2]);
			if ( $id )
				return $id;
		}

		// Check to see if we are using rewrite rules
		$rewrite = $wp_rewrite->wp_rewrite_rules();

		// Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
		if ( empty($rewrite) )
			return 0;

		// Get rid of the #anchor
		$url_split = explode('#', $url);
		$url = $url_split[0];

		// Get rid of URL ?query=string
		$url_split = explode('?', $url);
		$url = $url_split[0];

		// Add 'www.' if it is absent and should be there
		if ( false !== strpos(home_url(), '://www.') && false === strpos($url, '://www.') )
			$url = str_replace('://', '://www.', $url);

		// Strip 'www.' if it is present and shouldn't be
		if ( false === strpos(home_url(), '://www.') )
			$url = str_replace('://www.', '://', $url);

		// Strip 'index.php/' if we're not using path info permalinks
		if ( !$wp_rewrite->using_index_permalinks() )
			$url = str_replace( $wp_rewrite->index . '/', '', $url );

		if ( false !== strpos( trailingslashit( $url ), home_url( '/' ) ) ) {
			// Chop off http://domain.com/[path]
			$url = str_replace(home_url(), '', $url);
		} else {
			// Chop off /path/to/blog
			$home_path = parse_url( home_url( '/' ) );
			$home_path = isset( $home_path['path'] ) ? $home_path['path'] : '' ;
			$url = preg_replace( sprintf( '#^%s#', preg_quote( $home_path ) ), '', trailingslashit( $url ) );
		}

		// Trim leading and lagging slashes
		$url = trim($url, '/');

		$request = $url;
		$post_type_query_vars = array();

		foreach ( get_post_types( array() , 'objects' ) as $post_type => $t ) {
			if ( ! empty( $t->query_var ) )
				$post_type_query_vars[ $t->query_var ] = $post_type;
		}


		// Look for matches.
		$request_match = $request;
		foreach ( (array)$rewrite as $match => $query) {

			// If the requesting file is the anchor of the match, prepend it
			// to the path info.
			if ( !empty($url) && ($url != $request) && (strpos($match, $url) === 0) )
				$request_match = $url . '/' . $request;

			if ( preg_match("#^$match#", $request_match, $matches) ) {
				
				if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
					
					// Removed from url_to_postid. We don't care about page matches.
					// this is a verbose page match, lets check to be sure about it
					// if ( ! get_page_by_path( $matches[ $varmatch[1] ] ) )
					continue;
				}

				// Got a match.
				// Trim the query of everything up to the '?'.
				$query = preg_replace("!^.+\?!", '', $query);

				// Substitute the substring matches into the query.
				$query = addslashes(WP_MatchesMapRegex::apply($query, $matches));

				// Filter out non-public query vars
				global $wp;
				parse_str( $query, $query_vars );
				$query = array();
				foreach ( (array) $query_vars as $key => $value ) {
					if ( in_array( $key, $wp->public_query_vars ) ){
						$query[$key] = $value;

						// Added from url_to_postid to support post_id in permalink
						if( $key === 'p' ){
							return intval ( $value );
						}

						if ( isset( $post_type_query_vars[$key] ) ) {
							$query['post_type'] = $post_type_query_vars[$key];
							$query['name'] = $value;
						}
					}
				}

				/* Removed from url_to_postid. We can just use the post name as our key.
				// Do the query
				$query = new WP_Query( $query );
				if ( ! empty( $query->posts ) && $query->is_singular )
					return $query->post->ID;
				else
					return 0;
				*/

				if( isset( $query['name'] ) && $query['name'] ){
					return $query['name'];    
				} else {
					return 0;
				}
			}
		}
		return 0;
	}

	/*
	 *	Changes the theme to a fake one if the browser is supported and the given identifier matches 
	 *  what's been stored as a Narrative post.
	 *	
	 *	@param {String|int} post The post name or id.
	 *
	 */
	function set_theme_if_post_matches( $post ){
		if( $post == '' || !$this->is_supported_browser() ) {
			return;
		}

		$id = false;
		if( strncmp( $post, '?p=', 3 ) == 0 ) {
			$id = substr( $post, 3 );
		} else if( strncmp( $post, '?page_id=', 9 ) == 0 ) {
			$id = substr( $post, 9 );
		} else if( is_int( $post ) ) {
			$id = $post;
		}

		if( $id ) {
			$template = $this->narrative_template_for_id( $id );
		} else {
			$template = $this->narrative_template_for_name( $post );
		}

		if( $template ) {
			$this->narrative_template = $template;
			add_filter( 'pre_option_stylesheet', array( &$this, 'get_stylesheet' ) );
			add_filter( 'pre_option_template', array( &$this, 'get_template' ) );
		}
	}

	/*
	 *  Whether the browser is supported by Narrative or not.
	 */
	public function is_supported_browser(){
		return $this->supported_browser;
	}

	/**
	 *	Gets the Narrative template (i.e. Puget) for the given post name or returns null.
	 *
	 */
	function narrative_template_for_name( $name ){
		// Locate the matching index for the current page
		foreach( $this->options as $dt ){
			if ( $dt['url'] && $page == $dt['url'] ){
				return $dt['theme'];
			}
		}
		return null;
	}

	/**
	 *	Gets the Narrative template (i.e. Puget) for the given post id or returns null.
	 *
	 */
	function narrative_template_for_id( $id ){
		foreach( $this->options as $dt ){
			if ( $dt['id'] && $id == $dt['id'] ){
				return $dt['theme'];
			}
		}
		return null;
	}

	function get_stylesheet() {
		return 'narrative-fake-stylesheet';
	}

	function get_template() {
		return 'narrative-fake-template';
	}

	/** 
	 * Switches the template file (the actual HTML) to the one in this plugin, not to the theme's template and enqueues some
	 * scripts/styles.
	 *
	 */
	function template_include( $template ) {
		// Check if we are supposed to change this template
		if( $this->get_narrative_template() ) {

			if( $this->is_supported_browser() ) {
				add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_files' ), 10000 );
				remove_action('wp_head', '_admin_bar_bump_cb'); // Removes the admin bar
				$template = dirname( __FILE__ ) . '/theme/single-narrative.php';
			}

			// Still print some things if browser is not supported.
			add_action( 'wp_print_scripts', array( &$this, 'print_inline' ) );    	
		}

		return $template;
	}

	/*
	 *	Gets the Narrative template (i.e. Puget) if already determined or does a check against stored values
	 *  for the current queried object.
	 *
	 *	@return {String|null} Returns the Narrative template to use (i.e. Puget) or null if none.
	 */
	public function get_narrative_template(){
		// Haven't figured out whether to use Narrative
		if( ! isset( $this->narrative_template ) ) {
			$id = get_queried_object_id();
			if( $id && is_single( $id ) ) {
				$this->narrative_template = $this->narrative_template_for_id( $id );
			}
		}
		return $this->narrative_template;
	}

	/*
	 *	De/enqueue the JS/CSS files needed and not needed.
	 */
	function enqueue_files() {
		global $wp_styles;

		// Dequeue all enqueued styles by default
		$default_dequeue_styles = array();
		if ( is_a( $wp_styles, 'WP_Styles' ) ) {
			foreach( $wp_styles->queue as $key => $value ){
				array_push( $default_dequeue_styles, $value );
			}
		}

		/**
		 * Filter the styles to dequeue on Storyform posts.
		 *
		 * @param array $styles The styles that will be dequeued. Defaults to all styles.
		 */
		$dequeue_styles = apply_filters( 'storyform-dequeue-styles', $default_dequeue_styles );

		if ( $dequeue_styles && is_array( $dequeue_styles ) ) {
			foreach( $dequeue_styles as $handle ) {
				wp_dequeue_style( $handle );
			}
		}

		// Most scripts by default don't conflict with Storyform
		$default_dequeue_scripts = array();
		 
		/**
		 * Filter the scripts to dequeue on Storyform posts.
		 *
		 * @param array $scripts The scripts that will be dequeued. Defaults to none.
		 */
		$dequeue_scripts = apply_filters( 'storyform-dequeue-scripts', $default_dequeue_scripts );
		 
		if ( $dequeue_scripts && is_array( $dequeue_scripts ) ) {
			foreach ( $dequeue_scripts as $handle ) {
				wp_dequeue_script( $handle );
			}
		}

		$instance = Narrative_Api::get_instance();
		wp_enqueue_script( 'narrative_js', $instance->get_js(), array(), false, false );
		wp_enqueue_style( 'narrative_css', $instance->get_css(), array(), false, false );

	}

	/*
	 *	Specify the Narrative template to use, version and host in the client HTML or show a browser update alert
	 */
	function print_inline() {
		if( $this->is_supported_browser() ) {
		?>
			 <script>
				var _template = {
					group: '<?php echo $this->get_narrative_template() ?>',
					version: 'v<?php echo Narrative_Api::get_instance()->get_version() ?>',
					host: '<?php echo Narrative_Api::get_instance()->get_hostname() ?>'
				};

				/*-- START RENDER SCRIPT --*/!function(a){function b(a,b){var c=new XMLHttpRequest;c.onreadystatechange=function(){4===c.readyState&&(c.status>=200&&c.status<300?b(null,c):b(c),c.onreadystatechange=function(){})},c.open("GET",a.uri,!0),c.send()}document.documentElement.className+=" js";var c=void 0===_template.host?"http://narrative.co":_template.host,d=_template.version||"v0.3",e=c+"/"+d+"/render/"+a._template.group+"?uri="+encodeURIComponent(document.location.href)+"&lastModified="+encodeURIComponent(document.lastModified)+"&templateGroup="+encodeURIComponent(_template.group)+"&width="+window.innerWidth+"&height="+window.innerHeight+"&deviceWidth="+window.screen.width+"&deviceHeight="+window.screen.height;a.App={Data:{render:{callback:function(){},data:null,uri:e}}},b({uri:e},function(b,c){if(c){var d=a.App.Data.render.data=JSON.parse(c.responseText);a.App.Data.render.callback(null,d),a.App.Data.render.callback=function(){}}else a.App.Data.render.callback(b),a.App.Data.render.callback=function(){}})}(this);/*-- END RENDER SCRIPT --*/
			</script>
		<?php } else { ?>

			<script type="text/javascript"> 
				var $buoop = {}; 
				$buoop.ol = window.onload; 
				window.onload=function(){ 
				 try {if ($buoop.ol) $buoop.ol();}catch (e) {} 
				 var e = document.createElement("script"); 
				 e.setAttribute("type", "text/javascript"); 
				 e.setAttribute("src", "//browser-update.org/update.js"); 
				 document.body.appendChild(e); 
				} 
			</script> 

		<?php
		}
	}

}