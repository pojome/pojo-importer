<?php
/*
Plugin Name: Pojo Import
Plugin URI: http://pojo.me/
Description: ...
Author: Pojo Team
Author URI: http://pojo.me/
Version: 1.0.0
Text Domain: pojo-import
Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'POJO_IMPORT__FILE__', __FILE__ );
define( 'POJO_IMPORT_BASE', plugin_basename( POJO_IMPORT__FILE__ ) );
define( 'POJO_IMPORT_URL', plugins_url( '/', POJO_IMPORT__FILE__ ) );
define( 'POJO_IMPORT_ASSETS_PATH', plugin_dir_path( POJO_IMPORT__FILE__ ) . 'assets/' );
define( 'POJO_IMPORT_ASSETS_URL', POJO_IMPORT_URL . 'assets/' );

final class Pojo_Import {

	/**
	 * @var Pojo_Import The one true Pojo_Import
	 * @since 1.0.0
	 */
	private static $_instance = null;

	/**
	 * @var Pojo_Import_Actions
	 */
	public $actions;

	public function load_textdomain() {
		load_plugin_textdomain( 'pojo-import', false, basename( dirname( __FILE__ ) ) . '/languages' );
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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'pojo-import' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'pojo-import' ), '1.0.0' );
	}

	/**cd
	 * @return Pojo_Import
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new Pojo_Import();

		return self::$_instance;
	}

	public function register_settings() {
		include( 'includes/class-pojo-import-settings.php' );
		new Pojo_Import_Settings();
	}

	public function bootstrap() {
		// This plugin for Pojo Themes..
		// TODO: Add notice for non-pojo theme
		if ( ! class_exists( 'Pojo_Core' ) )
			return;

		add_action( 'pojo_framework_base_settings_included', array( &$this, 'register_settings' ) );
		
		include( 'includes/class-pojo-import-actions.php' );
		
		$this->actions = new Pojo_Import_Actions();
	}

	private function __construct() {
		add_action( 'init', array( &$this, 'bootstrap' ) );
		add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
	}

}

Pojo_Import::instance();
// EOF