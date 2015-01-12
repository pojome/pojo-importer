<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pojo_Import_Settings {
	
	protected $_capability = 'manage_options';
	
	protected $_message = '';
	
	protected $_print_footer_scripts = false;

	public function setup_import() {
		define( 'WP_LOAD_IMPORTERS', true );

		require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! class_exists( 'WP_Import' ) )
			require_once( dirname( __FILE__ ) . '/wordpress-importer/wordpress-importer.php' );

		require_once( 'class-pojo-import-handler.php' );
	}

	public function get_content_langs() {
		return array(
			'en' => __( 'English', 'pojo-import' ),
			'he' => __( 'Hebrew', 'pojo-import' ),
		);
	}

	public function get_content_path( $lang ) {
		return get_template_directory() . sprintf( '/assets/demo/content-%s.xml', $lang );
	}

	public function get_widgets_content_path( $lang ) {
		return get_template_directory() . sprintf( '/assets/demo/widgets-%s.json', $lang );
	}
	
	public function register_menu() {
		add_submenu_page(
			'pojo-general',
			__( 'Import', 'pojo-import' ),
			__( 'Import', 'pojo-import' ),
			$this->_capability,
			'pojo-import',
			array( &$this, 'display_page' )
		);
	}

	public function display_page() {
		$this->_print_footer_scripts = true;
		?>
		<div class="wrap">

			<div id="icon-themes" class="icon32"></div>
			<h2><?php _e( 'Import Content', 'pojo-import' ); ?></h2>
			
			<?php if ( ! empty( $this->_message ) ) : ?>
			<div class="updated"><p><?php echo $this->_message; ?></p></div>
			<?php endif; ?>

			<form id="pojo-import-content">
				<input type="hidden" name="action" value="pojo_do_import" />
				<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( 'pojo-import-content' ) ?>" />
				
				<div>
					<label>
						<?php _e( 'Lang:' ,'pojo-import' ); ?>
						<select name="lang">
							<?php foreach ( $this->get_content_langs() as $lang_key => $lang_title ) : ?>
								<option value="<?php echo $lang_key; ?>"><?php echo $lang_title; ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				
				<div>
					<p><?php _e( 'Desc text....', 'pojo-import' ); ?></p>
					<p><button type="submit" class="button"><?php _e( 'Import', 'pojo-import' ); ?></button></p>
				</div>
			</form>
			
		</div>
	<?php
	}

	public function admin_footer() {
		if ( ! $this->_print_footer_scripts )
			return;
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( '#pojo-import-content' ).on( 'submit', function( e ) {
					var $thisForm = $( this );
					$thisForm
						.fadeOut( 'fast' )
						.after( '<div class="pojo-loading">Loading..</div>' );
					
					$.post( ajaxurl, $thisForm.serialize(), function( msg ) {
						$( 'div.pojo-loading' ).fadeOut( 'fast' );
						$thisForm.after( msg );
					} );
					return false;
				} );
			} );
		</script>
	<?php
	}
	
	public function ajax_pojo_do_import() {
		global $wpdb;
		
		if ( ! check_ajax_referer( 'pojo-import-content', '_nonce', false ) || ! current_user_can( $this->_capability ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'pojo-import' ) );
		}
		
		$langs = $this->get_content_langs();
		if ( ! isset( $_POST['lang'] ) || ! isset( $langs[ $_POST['lang'] ] ) )
			$_POST['lang'] = 'en';

		$this->setup_import();
		
		// Content:
		ob_start();

		$import = new Pojo_Import_Handler();
		$import->fetch_attachments = true;
		$import->import( $this->get_content_path( $_POST['lang'] ) );

		$import_log = ob_get_clean();
		
		// Widgets:
		$widgets = file_get_contents( $this->get_widgets_content_path( $_POST['lang'] ) );
		$widgets = json_decode( $widgets, true );
		
		if ( ! empty( $widgets ) ) {
			foreach ( $widgets as $key => $value ) {
				update_option( $key, $value );
			}
		}

		// Menus:
		$menus = array(
			// location => slug
			'primary' => 'main',
			'primary_mobile' => 'main',
		);

		$nav_menu_locations = get_nav_menu_locations();

		$nav_menus = wp_get_nav_menus();
		$registered_nav_menus = get_registered_nav_menus();
		if ( ! empty( $registered_nav_menus ) ) {
			foreach ( $registered_nav_menus as $location_key => $location_title ) {
				if ( isset( $menus[ $location_key ] ) ) {
					foreach ( $nav_menus as $nav_menu ) {
						if ( $menus[ $location_key ] === $nav_menu->name ) {
							$nav_menu_locations[ $location_key ] = $nav_menu->term_id;
						}
					}
				}
			}
		}
		set_theme_mod( 'nav_menu_locations', $nav_menu_locations );
		
		// Set Home Page
		$home_page_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT `ID` FROM %1$s
					WHERE `post_name` = \'%2$s\'
						AND `post_type` = \'page\'
				;',
				$wpdb->posts,
				'homepage'
			)
		);
		
		if ( ! is_null( $home_page_id ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_page_id );
		}
		
		$placeholder_ids = $import->generate_placeholders();
		if ( ! empty( $placeholder_ids ) ) {
			$meta_key = 'gallery_gallery';
			$galleries_ids = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT `post_id` FROM `%1$s`
						WHERE `meta_key` LIKE \'%2$s\'
					;',
					$wpdb->postmeta,
					$meta_key
				)
			);
			
			if ( ! empty( $galleries_ids ) ) {
				foreach ( $galleries_ids as $gallery_id ) {
					update_post_meta( $gallery_id, $meta_key, implode( ',', $placeholder_ids ) );
				}
			}
		}
		
		echo $import_log;
		
		die();
	}

	public function __construct() {
		if ( ! current_user_can( $this->_capability ) )
			return;
		
		add_action( 'admin_menu', array( &$this, 'register_menu' ), 450 );
		add_action( 'admin_footer', array( &$this, 'admin_footer' ) );

		add_action( 'wp_ajax_pojo_do_import', array( &$this, 'ajax_pojo_do_import' ) );
	}
	
}