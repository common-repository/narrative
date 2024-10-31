<?php

/**
 *  Helper function for filtering to non-empty items
 *
 */
function narrative_not_empty( $item ) {
	return !empty( $item );
}

/**
 *  Inserts scripts neeeded to communicate with the image text overlay selector UI which gets hosted in a thickbox.
 *
 */
function narrative_admin_enqueue_scripts( $hook ) {
	if( 'post.php' != $hook && 'post-new.php' != $hook && 'upload.php' != $hook ) {
		return;
	}
	
	wp_enqueue_style( "thickbox" );
	wp_enqueue_script( "thickbox" );

	wp_enqueue_style( "narrative-media", plugin_dir_url( __FILE__ ) . 'narrative-media.css' );
	wp_register_script( "narrative-media", plugin_dir_url( __FILE__ ) . 'narrative-media.js', array( 'thickbox' ) );
	wp_enqueue_script( "narrative-media");
}
add_action( 'admin_enqueue_scripts', 'narrative_admin_enqueue_scripts' );

/**
 *  Inserts configuration data for Narrative
 *
 */
function narrative_admin_print_scripts(){
	?>
	<script> 
		var narrative = narrative || {};
		narrative.url = "<?php echo esc_js( Narrative_API::get_instance()->get_hostname() ) ?>";
	</script>
	<?php
}
add_action( 'admin_print_scripts',  'narrative_admin_print_scripts' ) ;

/**
 *  Adds a button in the sidebar of the "Add Media" Media library view and Edit media pages. The field allows
 *  the publisher to add or edit what part of a photo can be overlaid with text.
 *
 */
function narrative_attachment_fields_to_edit( $form_fields, $post ){
	$url = esc_js( wp_get_attachment_url( $post->ID ) );
	$metadata = wp_get_attachment_metadata( $post->ID );
	$narrativeMeta = get_post_meta( $post->ID, 'narrative_text_overlay_areas', true );

	$form_fields['narrative_text_overlay_areas'] = array(
		'label' => __( "Text overlay areas" ),
		'input' => 'html',
		'html' => '<div>' .
			'<p class="narrative-overlay-count" data-textContent-multiple="' . __( "{{count}} text overlay area(s)" ) . '" data-textContent="' . __( "No text overlay areas." ) . '"></p>' .
			'<button class="button-primary" id="narrative-add-overlay" data-textContent-multiple="' . __("Edit text over area(s)") . '" data-textContent="' . __( "Add text overlay area" ) . '"></button>' .
			"<input type='hidden' id='narrative-text-overlay-areas' name='attachments[{$post->ID}][narrative_text_overlay_areas]' value='" . wp_kses_post( $narrativeMeta ) . "' />" .
			'<script> 
				narrative.attachment = { 
					url: "' . $url . '"
				};
				narrative.initAttachmentFields();
			</script>' .
		'</div>'
	);

	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'narrative_attachment_fields_to_edit', 10, 2 );


/**
 *  Admin ajax call which returns the overlay data for a given attachment
 *
 */
function narrative_get_overlay_areas( ) {
	$narrativeMeta = get_post_meta( intval( $_POST['attachment_id'] ), 'narrative_text_overlay_areas', true );
	echo $narrativeMeta;

	die(); 
}
add_action( 'wp_ajax_narrative_get_overlay_areas', 'narrative_get_overlay_areas' );


/**
 *  Admin ajax call to save overlay data to attachment
 *
 */
function narrative_save_overlay_areas( ) {
	update_post_meta( intval( $_POST['attachment_id'] ), 'narrative_text_overlay_areas', sanitize_text_field( $_POST['narrative_text_overlay_areas'] ));
	echo $_POST['areas'];
	die(); 
}
add_action( 'wp_ajax_narrative_save_overlay_areas', 'narrative_save_overlay_areas' );


/**
 *  Saves text overlay area data with the media post.
 *
 */
function narrative_attachment_field_credit_save( $post, $attachment ) {
	if( isset( $attachment['narrative_text_overlay_areas'] ) ) {
		update_post_meta( $post['ID'], 'narrative_text_overlay_areas', sanitize_text_field( $attachment['narrative_text_overlay_areas'] ) );
	}

	return $post;
}
add_filter( 'attachment_fields_to_save', 'narrative_attachment_field_credit_save', 10, 2 );

/**
 *
 * Reads a single item from the list of items coming out of the DB and converts it into a named strucure
 *
 */
function narrative_read_single_overlay ( $overlay ) {
	$parts = explode( ' ', $overlay );
	return array( 'shape' => $parts[0], 'x1' => $parts[1], 'y1' => $parts[2], 'x2' => $parts[3], 'y2' => $parts[4], 'classNames' => array_slice( $parts, 5 ) );
}

/**
 *
 *  Adds data(-)sources attributes to <img> tag when inserted into editor from Media library which 
 *  enables responsive images to choose the best image to load on the client.
 *
 */
if( ! function_exists( 'narrative_image_send_to_editor' )) :
function narrative_image_send_to_editor( $html, $id, $caption, $title, $align, $url, $size, $alt ) {
	$metadata = wp_get_attachment_metadata( $id );
	$sizeNames = array_keys( $metadata['sizes'] );
	$sizeNames[] = 'full';

	$full = wp_get_attachment_image_src( $id, 'full' );
	$fullAspect = $full[1] / $full[2];

	$datasources = array();
	foreach( $sizeNames as $name) {
		$attr = wp_get_attachment_image_src( $id, $name );
		$url = $attr[0];
		$width = $attr[1];
		$height = $attr[2];
		$aspect = $width / $height;

		// Only use scaled images not cropped images (pixel rounding can occur, thus the 0.01)
		if( $aspect > $fullAspect + 0.01 || $aspect < $fullAspect - 0.01) {  
			continue;
		}
		
		array_push( $datasources, $url . ' 1x ' . $width . 'w ' . $height . 'h' );
	}

	$textOverlay = get_post_meta( $id, 'narrative_text_overlay_areas', true );

	// Add data-source attribute to the <img> tag (whether or not its surrounded by caption shortcode)
	return preg_replace( '/<img /', '<img data-text-overlay="' . esc_attr( $textOverlay ). '" data-sources="' . esc_attr( join( ', ', $datasources ) ) . '" ', $html);

}
endif;
add_filter( 'image_send_to_editor', 'narrative_image_send_to_editor', 30, 8 );


/*
 * Only on Narrative posts do we create different HTML for the caption shortcode and replace src attribute.
 * 
 */
if ( ! function_exists( 'narrative_media_setup' ) ) :
function narrative_media_setup() {
	if( Narrative::template_in_use() ){
		add_filter( 'img_caption_shortcode', 'narrative_caption_shortcode', 10, 3 );
		add_filter( 'the_content', 'narrative_remove_src_attribute', 5 ); // Run before so there is a src attribute to push to data-sources where other lazyloaders might get to it first
	}
}
endif; // narrative_setup
add_action( 'after_setup_theme', 'narrative_media_setup' );

/*
 * Get caption shortcodes to use HTML5 standard <figure> elements. Also, use optional data-sources attribute to 
 * produce a responsive <picture> element to denote various image source candidates and <map> elements to denote text overlay
 * areas.
 * 
 */
if( ! function_exists( 'narrative_caption_shortcode' )) :
function narrative_caption_shortcode( $val, $attr, $content = null ) {
	extract( shortcode_atts( array(
		'id'            => '',
		'align'         => 'alignnone',
		'width'         => '',
		'caption'       => ''
	), $attr ) );

	if ( 1 > (int) $width || empty( $caption ) ) {
		return $content;
	}

	/**
	 *
	 * We do not add text overlay and responsive image data to the caption shortcode, but the internal
	 * <img> element instead. This is because the editor destroys any unknown attributes on the shortcode.
	 *
	 * Format of data-sources attribute:
	 *   Comma separated list of
	 *     <url> <pixelRatio>x <width>w <height>h
	 * 
	 * Format of data-attachment attribute:
	 *   Comma separated list of
	 *     <shape> <relativeX1> <relativeY1> <relativeX2> <relativeY2> <className1> <className2> ...
	 * 
	 *   Where coordinates are fractionally relative to the the width and height of the image
	 * 
	 * 
	 * Sample Output:
	 * <figure>
	 *      <picture>
	 *          <source data-sources=“<url> <pixelRatio>x <width>w <height>h” usemap=“#urlHash”/>
	 *          <source data-sources=“<url> <pixelRatio>x <width>w <height>h” usemap=“#urlHash2”/>
	 *          <noscript>
	 *              <img src="abc.jpg" />
	 *          </noscript>
	 *      </picture>
	 *      <figcaption>…</figcaption>
	 * </figure>
	 * <map name=“urlHash”>
	 *      <area shape=“rect” coords=“0,0,100,300” class=“dark-theme otherClass” />
	 *      <area shape=“rect” coords=“100,200,300,300” class=“dark-theme otherClass” />
	 * </map>
	 * <map name=“urlHash2”>
	 *      <area shape=“rect” coords=“0,0,100,300” class=“dark-theme otherClass” />
	 *      <area shape=“rect” coords=“100,200,300,300” class=“dark-theme otherClass” />
	 * </map>
	 */

	$mapshtml = '';

	// Check if there is text overlay data to lookup
	$textOverlayAttrPattern = '/data-text-overlay="([^\"]*)"/';
	if( preg_match( $textOverlayAttrPattern, $content, $overlayMatch ) ) {

		$overlayData = array_map( 'narrative_read_single_overlay', array_filter( explode( ",", $overlayMatch[1] ) , 'narrative_not_empty' ) );
		
		// Verify there is actual overlay data
		if( count( $overlayData ) ) {

			if( preg_match( '/data-sources="([^\"]+)"/', $content, $sourcesMatch ) ) {
				// Use a <picture> element so each source can have a usemap attribute to specify the overlay area for that image candidate
				$html = '<picture>';

				$candidates = narrative_parse_data_sources( $sourcesMatch[1] );
				if( $candidates ){
					foreach( $candidates as $candidate ) {
						// Generate <map> elements for candidate img by transform relative coordinates to actual pixel coordinates for the given image size
						$mapname = md5( $candidate['url'] ) . '-overlay';
						$usemap = 'usemap="#' . $mapname . '"';
						$mapshtml .= '<map name="' . $mapname .'">';
						foreach( $overlayData as $item ) {
							$shape = $item['shape'];
							$x1 = round( $item['x1'] * $candidate['width'] );
							$y1 = round( $item['y1'] * $candidate['height'] );
							$x2 = round( $item['x2'] * $candidate['width'] );
							$y2 = round( $item['y2'] * $candidate['height'] );
							$classNames = join( " ", $item['classNames']);

							$mapshtml .= '<area shape="' . $shape . '" coords="' . $x1 . ',' . $y1 . ',' . $x2 . ',' . $y2 . '" class="' . $classNames . '" />';
						}
						$mapshtml .= '</map>';
						
						$html .= '<source ' . 'data-sources="' . $candidate['url'] . ' ' . $candidate['pixelRatio'] . 'x ' . $candidate['width'] . 'w ' . $candidate['height'] . 'h" ' . $usemap . ' />';
					}
					$html .= '<noscript>' . do_shortcode( $content ) . '</noscript></picture>';
				} else {
					$html = do_shortcode( $content );
				}

			} else if( preg_match( '/width=[\\\'\"](\d+)[\\\'\"]/', $content, $widthMatches ) && preg_match( '/height=[\\\'\"](\d+)[\\\'\"]/', $content, $heightMatches ) ) {
				// We can just use the <img> element with a usemap attribute
				$width = intval( $widthMatches[1] );
				$height = intval( $heightMatches[1] );

				$mapname = 'a' . rand( 1, 1000000 ) . '-overlay'; // Just use a random name
				$usemap = 'usemap="#' . esc_attr( $mapname ) . '"';
				$mapshtml .= '<map name="' . esc_attr( $mapname ).'">';
				foreach( $overlayData as $item ) {
					$shape = $item['shape'];
					$x1 = round( $item['x1'] * $width );
					$y1 = round( $item['y1'] * $height );
					$x2 = round( $item['x2'] * $width );
					$y2 = round( $item['y2'] * $height );
					$classNames = join( " ", $item['classNames']);

					$mapshtml .= '<area shape="' . $shape . '" coords="' . $x1 . ',' . $y1 . ',' . $x2 . ',' . $y2 . '" class="' . $classNames . '" />';
				}
				$mapshtml .= '</map>';
				$html = preg_replace( '/<img /', '<img ' . $usemap . ' ', do_shortcode( $content ) );

			} else {

				// No width or height, no ability to specify text overlay because its relative
				$html = do_shortcode( $content );
			}

			$html = preg_replace( $textOverlayAttrPattern, '', $html );

		} else {
			// No actual overlay data, just keep the <img>
			$html = do_shortcode( $content );
		}
	} else {

		// If there is no text overlay data, we can just keep the data-sources on the <img>
		$html = do_shortcode( $content );
	}
	
	if ( $id ) {
		$idtag = 'id="' . esc_attr( $id ) . '" ';
	}
	return '<figure ' . $idtag . 'aria-describedby="figcaption_' . $id . '" >' . $html . '<figcaption>' . $caption . '</figcaption></figure>' . wp_kses_post( $mapshtml );
		
}
endif; // narrative_caption_shortcode

/**
 *	Parses the value of the data-sources attribute.
 *
 *	@param str {String} The data-source attribute value.
 *
 *	@return An array of associative arrays with the following properties for each candidate. FALSE if string is empty or no valid candidates.
 *				url, pixelRatio, width, height
 */
if( ! function_exists( 'narrative_parse_data_sources' )) :
function narrative_parse_data_sources( $str ){
	$candidates = array_filter( array_map( 'trim', explode( ",", $str ) ), 'narrative_not_empty' );
	$arr = array();
	if( count( $candidates ) ) {
		foreach( $candidates as $candidate ) {
			$parsed_candidate = array();

			if( preg_match( '/^([^\s]+)/', $candidate, $urlMatches ) 
				&& preg_match( '/\s+(\d+)x\s+/', $candidate, $pixelMatches )
				&& preg_match( '/\s+(\d+)w\s+/', $candidate, $widthMatches )
				&& preg_match( '/\s+(\d+)h/', $candidate, $heightMatches ) ) {
				
				$parsed_candidate['url'] = $urlMatches[1];
				$parsed_candidate['pixelRatio'] = $pixelMatches[1];
				$parsed_candidate['width'] = $widthMatches[1];
				$parsed_candidate['height'] = $heightMatches[1];

				array_push( $arr, $parsed_candidate );
			}
		}

		if( count( $arr ) ) {
			return $arr;
		}
		return FALSE;
	} else {
		return FALSE;
	}
}
endif; // narrative_parse_data_sources

/**
 *	Removes src attribute to do lazy-loading. Only replaces the src attribute with a placeholder if
 *  there is an equivalent src in data-sources attribute. Only replaces on narrative posts.
 *
 *	@param content {String} The content
 *
 *	@return The HTML content without the src attribute
 */
if( ! function_exists( 'narrative_remove_src_attribute' )) :
function narrative_remove_src_attribute( $content ){
	
	// Replace all imgs with src attribute
	return preg_replace_callback( '#<img([^>]+?)src=([\'"]?)([^\'"\s>]+)[\'"]?([^>]*)>#i', '_narrative_remove_src_attribute' , $content );
}
endif; // narrative_remove_src_attribute

if( ! function_exists( '_narrative_remove_src_attribute' )) :
function _narrative_remove_src_attribute( $srcMatches ) {
	$oneByoneUrl = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

	$imgHtml = $srcMatches[0];
	$before = $srcMatches[1];
	$quote = $srcMatches[2];
	$src = $srcMatches[3];
	$after = $srcMatches[4];


	if( preg_match( '#<img([^>]+?)width=[\'"]?([^\'">]+)[\'"]?([^>]*)>#i', $imgHtml, $widthMatches ) ){
		$width =  $widthMatches[2];	
	}
	if( preg_match( '#<img([^>]+?)height=[\'"]?([^\'">]+)[\'"]?([^>]*)>#i', $imgHtml, $heightMatches ) ) {
		$height =  $heightMatches[2];	
	}
	
	// Match data-sources attribute
	if ( preg_match( '#<img([^>]+?)data-sources=[\'"]?([^\'">]+)[\'"]?([^>]*)>#i', $imgHtml, $dataSourceMatches ) ) {
		$dataSources = $dataSourceMatches[2];

		$candidates = narrative_parse_data_sources( $dataSources );

		$hasSameDataSource = false;
		foreach( $candidates as $candidate ){
			if( $candidate['url'] === $src) {
				$hasSameDataSource = true;
			}
		}

		// Just replace the src with the placeholder
		if( $hasSameDataSource ) {
			return '<img' . $before . 'src=' . $quote . $oneByoneUrl . $quote . $after . '>';
		
		// Replace the src with the placeholder and inject a new data-sources candidate
		} else if( isset( $width ) && isset( $height ) ) {
			$result = preg_replace( '#data-sources=[\'"]?([^\'">]+)[\'"]?#', 'data-sources=' . $quote . $dataSources . ',' . $src . ' 1x ' . $width . 'w ' . $height . 'h' . $quote, $imgHtml );
			$result = preg_replace( '#src=[\'"]?([^\'">]+)[\'"]?#', 'src=' . $quote . $oneByoneUrl . $quote, $result );
			return $result;

		// Can't do anything since we don't know the size
		} else {
			return $imgHtml;
		}
	} else {
		// Create the data-sources for things like external src urls
		if ( isset( $width ) && isset( $height ) ) {
			return '<img data-sources="' . $src . ' 1x ' . $width . 'w ' . $height . 'h"' . $before . 'src=' . $quote . $oneByoneUrl . $quote . $after . '>';
		
		// Can't do anything since we don't know the size
		} else {
			return $imgHtml;
		}
		
	}
}
endif;

?>