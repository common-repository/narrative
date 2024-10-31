<?php
/**
 * Checks if Narrative supports the User Agent
 *
 * @param {string} userAgent The user agent string to check
 */
function narrative_check_user_agent( $userAgent ) {

	$patterns = array(
		'Trident' 		=> array( 
			'pattern' 		=> '/Trident\/([\d.]+)/i',
			'minVersion'	=> 6 // IE 10+
		),
		'AppleWebKit' 	=> array(
			'pattern'		=> '/AppleWebKit\/([\d.]+)/i',
			'minVersion'	=> 537 // Safari 6.1+ (which means iOS 7+), Chrome 21+, Opera 15+
		),
		'Gecko'			=> array(
			'pattern'		=> '/rv\:([\d.]+).*Gecko\/\S+/i',
			'minVersion'	=> 23 // Firefox 23+
		)
	);

  	$matches = array();
  	foreach ( $patterns as $name => $engine ) {
  		if( preg_match( $engine['pattern'] , $userAgent, $matches ) ) {
  			$version = floatval( $matches[1] );
	  		if( $version >= $engine['minVersion'] ) {
	  			return TRUE;
	  		} else {
	  			return FALSE;
	  		}
	  	}
  	}

  	return FALSE;
}




?>