<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pojo_Importer_Settings {
	
	protected $_capability = 'manage_options';
	
	protected $_print_footer_scripts = false;
	
	protected $_saved_files = array();

	public function remove_temp_files() {
		if ( empty( $this->_saved_files ) )
			return;
		
		foreach ( $this->_saved_files as $file ) {
			if ( file_exists( $file['file'] ) )
				@unlink( $file['file'] );
		}
		
		$this->_saved_files = array();
	}

	public function setup_import() {
		if ( defined( 'WP_LOAD_IMPORTERS' ) )
			return;
		
		define( 'WP_LOAD_IMPORTERS', true );

		require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! class_exists( 'WP_Import' ) )
			require_once( dirname( __FILE__ ) . '/wordpress-importer/wordpress-importer.php' );

		require_once( 'class-pojo-importer-handler.php' );
	}

	public function get_content_langs() {
		return array(
			'en' => __( 'English', 'pojo-importer' ),
			'he' => __( 'Hebrew', 'pojo-importer' ),
		);
	}

	public function get_default_lang() {
		$default_langs = array(
			'en_US' => 'en',
			'he_IL' => 'he',
		);
		
		if ( isset( $default_langs[ get_locale() ] ) )
			return $default_langs[ get_locale() ];
		
		return 'en';
	}

	public function get_files_list() {
		static $return = null;
		
		if ( is_null( $return ) ) {
			$response = wp_remote_post(
				'http://pojo.me/',
				array(
					'sslverify' => false,
					'timeout' => 30,
					'body' => array(
						'pojo_action' => 'get_import_files',
						'theme' => Pojo_Core::instance()->licenses->updater->theme_name,
						'license' => Pojo_Core::instance()->licenses->settings->get_license_key(),
						'lang' => $_POST['lang'],
					)
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				$return = array();
				return $return;
			}
			
			$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $response_data['success'] )
				$return = $response_data['data'];
			else
				$return = array();
		}
		
		return $return;
	}
	
	protected function _upload_file( $url ) {
		$has_ms_filter = false;

		if ( has_filter( 'wp_upload_bits', 'upload_is_file_too_big' ) ) {
			remove_filter( 'wp_upload_bits', 'upload_is_file_too_big' );
			$has_ms_filter = true;
		}

		add_filter( 'upload_mimes', array( &$this, 'filter_add_extra_mime_types' ), 30, 2 );
		$upload = wp_upload_bits(
			pathinfo( $url, PATHINFO_BASENAME ),
			null,
			file_get_contents( $url )
		);
		remove_filter( 'upload_mimes', array( &$this, 'filter_add_extra_mime_types' ), 30 );

		if ( $has_ms_filter ) {
			add_filter( 'wp_upload_bits', 'upload_is_file_too_big' );
		}

		// Save the file to unlink after import action
		$this->_saved_files[] = $upload;

		return $upload['file'];
	}
	
	protected function get_remote_content_file( $type, $lang ) {
		if ( ! in_array( $type, array( 'content', 'customizer', 'widgets' ) ) )
			return '';

		$files = $this->get_files_list();
		if ( empty( $files ) )
			return '';

		if ( ! isset( $files[ $lang ] ) || ! isset( $files[ $lang ][ $type ] ) )
			return '';
		
		$url = $files[ $lang ][ $type ];

		return $this->_upload_file( $url );
	}

	public function filter_add_extra_mime_types( $mime_types, $user ) {
		$mime_types['xml'] = 'application/xml';
		$mime_types['json'] = 'application/json';
		$mime_types['zip'] = 'application/zip';
		
		return $mime_types;
	}

	public function get_content_path( $lang ) {
		return $this->get_remote_content_file( 'content', $lang );
	}

	public function get_widgets_content_path( $lang ) {
		return $this->get_remote_content_file( 'widgets', $lang );
	}

	public function get_customizer_content_path( $lang ) {
		return $this->get_remote_content_file( 'customizer', $lang );
	}
	
	public function register_menu() {
		add_submenu_page(
			'pojo-home',
			__( 'Demo Import', 'pojo-importer' ),
			__( 'Demo Import', 'pojo-importer' ),
			$this->_capability,
			'pojo-importer',
			array( &$this, 'display_page' )
		);
	}

	public function display_page() {
		$this->_print_footer_scripts = true;
		?>
		<div class="wrap">

			<div id="icon-themes" class="icon32"></div>
			<h2><?php _e( 'Demo Import', 'pojo-importer' ); ?></h2>

			<p><?php _e( 'Using the demo import allows you to import all the demo content (Posts, Pages, Galleries, Slideshows, WooCommerce), Widgets, Menus, Customizer and Front Page.', 'pojo-importer' ); ?></p>
			
			<p><?php printf( __( 'If you want to import all of the <a href="%s" target="_blank">WooCommerce</a> content, you must install the plugin before importing.', 'pojo-importer' ), 'https://wordpress.org/plugins/woocommerce/' ); ?></p>

			<p><?php _e( 'Note: Due to copyright reasons, demo images will be replaced a placeholder image.', 'pojo-importer' ); ?></p>
			
			<form id="pojo-importer-content">
				<input type="hidden" name="action" value="pojo_do_import" />
				<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( 'pojo-importer-content' ) ?>" />
				
				<div>
					<label>
						<?php _e( 'Choose your language', 'pojo-importer' ); ?>:
						<select name="lang">
							<?php foreach ( $this->get_content_langs() as $lang_key => $lang_title ) : ?>
								<option value="<?php echo $lang_key; ?>"<?php selected( $this->get_default_lang(), $lang_key ); ?>><?php echo $lang_title; ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				
				<div>
					<label>
						<input type="checkbox" name="content" value="yes" checked />
						<?php _e( 'The demo content (posts, pages, galleries, slideshows, WooCommerce)', 'pojo-importer' ); ?>
					</label>
				</div>
				
				<div>
					<label>
						<input type="checkbox" name="widgets" value="yes" checked />
						<?php _e( 'Widgets', 'pojo-importer' ); ?>
					</label>
				</div>
				
				<div>
					<label>
						<input type="checkbox" name="menus" value="yes" checked />
						<?php _e( 'Menus', 'pojo-importer' ); ?>
					</label>
				</div>
				
				<div>
					<label>
						<input type="checkbox" name="customizer" value="yes" checked />
						<?php _e( 'Customizer', 'pojo-importer' ); ?>
					</label>
				</div>
				
				<div>
					<label>
						<input type="checkbox" name="front_page" value="yes" checked />
						<?php _e( 'Front Page', 'pojo-importer' ); ?>
					</label>
				</div>
				
				<?php if ( Pojo_Compatibility::is_revslider_installer() ) : ?>
					<div>
						<label>
							<input type="checkbox" name="revslider" value="yes" checked />
							<?php _e( 'Revolution Slider', 'pojo-importer' ); ?>
						</label>
					</div>
				<?php endif; ?>
				
				<div>
					<p style="color: #ff0000;"><?php _e( 'Please Note: If there is content in the existing site, you may not want to import the demo content, it could change the content structure.', 'pojo-importer' ); ?></p>
					<p><button type="submit" class="button"><?php _e( 'Import', 'pojo-importer' ); ?></button></p>
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
			jQuery( document ).ready( function($) {				
				$( '#pojo-importer-content' ).on( 'submit', function(e) {
					var $thisForm = $( this );
					$thisForm
						.fadeOut( 'fast' )
						.after( '<div class="pojo-loading"><span class="spinner"></span> <?php _e( 'Loading', 'pojo-importer' ); ?>..</div>' );
					
					$.post( ajaxurl, $thisForm.serialize(), function( msg ) {
						$( 'div.pojo-loading' ).fadeOut( 'fast' );
						//$thisForm.after( msg );
						var $textarea = $( '<div></div>' );
						$textarea
							.html( msg )
							.addClass( 'widget-top' )
							.css( {
								width: '80%',
								padding: '15px',
								'max-height': '350px',
								'overflow-y': 'scroll'
							} );
						$thisForm
							.after( $textarea )
							.after( '<p><?php printf( __( 'All done. <a href="%s">Have fun!</a>', 'pojo-importer' ), home_url() ); ?></p>' );
					} );
					return false;
				} );
			} );
		</script>
	<?php
	}
	
	public function ajax_pojo_do_import() {
		global $wpdb;
		
		if ( ! check_ajax_referer( 'pojo-importer-content', '_nonce', false ) || ! current_user_can( $this->_capability ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'pojo-importer' ) );
		}
		
		$langs = $this->get_content_langs();
		if ( ! isset( $_POST['lang'] ) || ! isset( $langs[ $_POST['lang'] ] ) )
			$_POST['lang'] = 'en';

		$this->setup_import();

		$import_log = '';
		
		if ( isset( $_POST['content'] ) && 'yes' === $_POST['content'] ) {
			// Content:
			$import_log .= $this->import_content( $_POST['lang'] );
		}
		
		if ( isset( $_POST['customizer'] ) && 'yes' === $_POST['customizer'] ) {
			// Customizer:
			$this->import_customizer( $_POST['lang'] );
		}
		
		if ( isset( $_POST['widgets'] ) && 'yes' === $_POST['widgets'] ) {
			// Widgets:
			$this->import_widgets( $_POST['lang'] );
		}
		
		if ( isset( $_POST['menus'] ) && 'yes' === $_POST['menus'] ) {
			// Menus:
			$this->import_menus();
		}

		if ( isset( $_POST['front_page'] ) && 'yes' === $_POST['front_page'] ) {
			// Set Home Page
			$this->import_front_page();
		}

		if ( isset( $_POST['revslider'] ) && 'yes' === $_POST['revslider'] ) {
			// RevSlider
			$this->import_revslider( $_POST['lang'] );
		}
		
		// Remove temp files
		$this->remove_temp_files();
		
		echo $import_log;
		
		die();
	}

	public function import_content( $lang ) {
		global $wpdb;
		
		ob_start();

		$import                    = new Pojo_Importer_Handler();
		$import->fetch_attachments = true;
		$import->import( $this->get_content_path( $lang ) );

		$import_log = ob_get_clean();

		// Galleries Placeholders
		$placeholder_ids = $import->generate_placeholders();
		if ( ! empty( $placeholder_ids ) ) {
			$meta_key      = 'gallery_gallery';
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

		update_option( 'pojo_has_import_content_data_' . strtolower( Pojo_Core::instance()->licenses->updater->theme_name ), 'true' );
		
		return $import_log;
	}

	public function import_customizer( $lang ) {
		$customizer_options = json_decode( file_get_contents( $this->get_customizer_content_path( $lang ) ), true );

		if ( ! empty( $customizer_options ) ) {
			foreach ( $customizer_options as $key => $value ) {
				set_theme_mod( $key, $value );
			}
		}
	}

	public function import_widgets( $lang ) {
		$widgets = file_get_contents( $this->get_widgets_content_path( $lang ) );
		$widgets = json_decode( $widgets, true );

		if ( ! empty( $widgets ) ) {
			foreach ( $widgets as $key => $value ) {
				update_option( $key, $value );
			}
		}
	}

	public function import_menus() {
		$menus = array(
			// location => slug
			'primary' => 'main',
			'primary_mobile' => 'main',
		);

		$nav_menu_locations = get_nav_menu_locations();

		$nav_menus            = wp_get_nav_menus();
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
	}

	public function import_front_page() {
		global $wpdb;
		
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
	}

	public function import_revslider( $lang ) {
		if ( ! Pojo_Compatibility::is_revslider_installer() )
			return;

		$files = $this->get_files_list();
		if ( empty( $files ) )
			return;

		if ( ! isset( $files[ $lang ] ) || ! isset( $files[ $lang ]['revslider'] ) )
			return;

		$revslider = new RevSlider();
		
		foreach ( $files[ $lang ]['revslider'] as $slider ) {
			$temp_file = $this->_upload_file( $slider );
			$revslider->importSliderFromPost( 'true', 'true', $temp_file );
		}
	}

	public function __construct() {
		if ( ! current_user_can( $this->_capability ) )
			return;
		
		add_action( 'admin_menu', array( &$this, 'register_menu' ), 450 );
		add_action( 'admin_footer', array( &$this, 'admin_footer' ) );

		add_action( 'wp_ajax_pojo_do_import', array( &$this, 'ajax_pojo_do_import' ) );
	}
	
}