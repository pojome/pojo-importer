<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pojo_Import_Actions {

	public function get_content_path() {
		return get_template_directory() . '/assets/demo/content.xml';
	}

	public function do_import() {
		$this->setup_import();
		
		ob_start();
		
		$import = new Pojo_Import_Handler();
		$import->fetch_attachments = true;
		$import->import( $this->get_content_path() );
		
		$import_log = ob_get_clean();
		
		return $import_log;
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

	public function __construct() {}
	
}