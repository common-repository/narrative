<?php

/**
 * Singleton that handles versioning and end-points for the Narrative API
 *
 */
class Narrative_Api {
    private $version        = '0.3';
    private $staticHostname = 'http://static.storyform.co';
    private $hostname       = 'http://storyform.co';
    private $textdomain     = 'default';

    protected static $instance = false;

    public static function get_instance() {
        if( !self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function get_version(){
        return $this->version;
    }

    function get_css(){
        return $this->staticHostname . '/v' . $this->version . '/css/read.css';
    }

    function get_js(){
        return $this->staticHostname . '/v' . $this->version . '/js/read.js';
    }

    function get_version_directory(){
        return $this->hostname . '/v' . $this->version . '/';
    }

    function get_hostname(){
        return $this->hostname;
    }

    function get_textdomain(){
        return $this->textdomain;
    }


}

?>