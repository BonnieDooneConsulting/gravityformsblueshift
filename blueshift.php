<?php
/*
Plugin Name: Gravity Forms Blueshift
Plugin URI: https://oxfordclub.com
Description: Integrates Gravity Forms with Blueshift, allowing form submissions to be automatically sent as mailings from your Blueshift account.
Version: 0.0.1
Author: Bonnie Doone Consulting, LLC
Author URI: https://bonniedooneconsulting.com
*/

define( 'GF_BLUESHIFT_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'GF_Blueshift_Bootstrap', 'load' ), 5 );

/**
 * Class GF_Blueshift_Bootstrap
 *
 * Load up the blueshift API and other items
 */
class GF_Blueshift_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
            return;
        }

        require_once( 'class-gf-blueshift.php' );

        GFAddOn::register( 'GFBlueshift' );
    }

}

function gf_simple_feed_addon() {
    return GFBlueshift::get_instance();
}