<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pojo_Import_Actions {

	public function init() {
		if ( empty( $_GET['ys-import-test'] ) )
			return;
		
		$this->setup_import();
		
		ob_start();
		
		$import = new Pojo_Import_Handler();
		$import->fetch_attachments = true;
		$import->import( dirname( __FILE__ ) . '/file.xml' );
		
		$import_log = ob_get_clean();
		
		
		
		die;
	}

	public function setup_import() {
		define( 'WP_LOAD_IMPORTERS', true );

		require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		
		if ( ! class_exists( 'WP_Import' ) )
			require_once( dirname( __FILE__ ) . '/wordpress-importer/wordpress-importer.php' );
		
		require_once( 'class-pojo-import-handler.php' );
	}

	public function __construct() {
		add_action( 'admin_init', array( &$this, 'init' ) );
	}
	
}