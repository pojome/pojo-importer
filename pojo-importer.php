<?php
/*
Plugin Name: Pojo Importer
Plugin URI: http://pojo.me/
Description: Import all the demo content (Posts, Pages, Galleries, Slideshows), Widgets, Menus, Customizer and Front Page.
Author: Pojo Team
Author URI: http://pojo.me/
Version: 1.1.4
Text Domain: pojo-importer
Domain Path: /languages/


This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'POJO_IMPORTER__FILE__', __FILE__ );
define( 'POJO_IMPORTER_BASE', plugin_basename( POJO_IMPORTER__FILE__ ) );
define( 'POJO_IMPORTER_URL', plugins_url( '/', POJO_IMPORTER__FILE__ ) );
define( 'POJO_IMPORTER_ASSETS_PATH', plugin_dir_path( POJO_IMPORTER__FILE__ ) . 'assets/' );
define( 'POJO_IMPORTER_ASSETS_URL', POJO_IMPORTER_URL . 'assets/' );

final class Pojo_Importer {

	/**
	 * @var Pojo_Importer The one true Pojo_Importer
	 * @since 1.0.0
	 */
	private static $_instance = null;

	/**
	 * @var Pojo_Importer_Settings
	 */
	public $settings;

	public function load_textdomain() {
		load_plugin_textdomain( 'pojo-importer', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'pojo-importer' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'pojo-importer' ), '1.0.0' );
	}

	/**
	 * @return Pojo_Importer
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new Pojo_Importer();

		return self::$_instance;
	}

	public function register_settings() {
		include( 'includes/class-pojo-importer-settings.php' );
		$this->settings = new Pojo_Importer_Settings();
	}

	public function bootstrap() {
		// This plugin for Pojo Themes..
		// TODO: Add notice for non-pojo theme
		if ( ! class_exists( 'Pojo_Core' ) )
			return;

		add_action( 'pojo_framework_base_settings_included', array( &$this, 'register_settings' ) );
	}

	private function __construct() {
		add_action( 'init', array( &$this, 'bootstrap' ) );
		add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
	}

}

Pojo_Importer::instance();
// EOF