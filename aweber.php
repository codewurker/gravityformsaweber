<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/*
Plugin Name: Gravity Forms AWeber Add-On
Plugin URI: https://gravityforms.com
Description: Integrates Gravity Forms with AWeber, allowing form submissions to be automatically sent to your AWeber account.
Version: 4.0.0
Author: Gravity Forms
Author URI: https://gravityforms.com
License: GPL-2.0+
Text Domain: gravityformsaweber
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2023 Rocketgenius, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

/**
 * Current version of AWeber Add-On.
 */
define( 'GF_AWEBER_VERSION', '4.0.0' );

// If Gravity Forms is loaded, bootstrap the AWeber Add-On.
add_action( 'gform_loaded', array( 'GF_AWeber_Bootstrap', 'load' ), 5 );

/**
 * Class GF_AWeber_Bootstrap
 *
 * Handles the loading of the AWeber Add-On and registers it with the Add-On Framework.
 */
class GF_AWeber_Bootstrap {

	/**
	 * If the Add-On Framework exists, load AWeber Add-On.
	 *
	 * @access public
	 * @static
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-aweber.php' );

		GFAddOn::register( 'GFAWeber' );

	}

}

/**
 * Returns an instance of the GFAWeber class.
 *
 * @see GFAWeber::get_instance()
 *
 * @return GFAWeber
 */
function gf_aweber() {
	return GFAWeber::get_instance();
}
