<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pojo_Importer_Handler extends Pojo_WP_Import {

	const PLACEHOLDER_SLUG = 'pojo-placeholder';

	protected $placeholder_image_id = null;
	
	protected $placeholder_ids = array();
	
	protected function _get_placeholder_from_media() {
		global $wpdb;
		
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT `ID` FROM %1$s
					WHERE `post_name` = \'%2$s\'
						AND `post_type` = \'attachment\'
				;',
				$wpdb->posts,
				self::PLACEHOLDER_SLUG
			)
		);
		
		if ( ! is_null( $post_id ) )
			return $post_id;
		
		return false;
	}

	public function generate_placeholders() {
		if ( empty( $this->placeholder_ids ) ) {
			if ( ! function_exists( 'WP_Filesystem' ) )
				require_once ABSPATH . 'wp-admin/includes/file.php';

			global $wp_filesystem;
			WP_Filesystem();
			
			foreach ( range( 1, 5 ) as $index ) {
				$upload = wp_upload_bits(
					sprintf( 'pojo-placeholder-%d.png', $index ),
					null,
					$wp_filesystem->get_contents( POJO_IMPORTER_ASSETS_PATH . sprintf( 'images/placeholder-%d.png', $index ) )
				);

				$post = array();
				$info = wp_check_filetype( $upload['file'] );
				if ( $info )
					$post['post_mime_type'] = $info['type'];
				else
					continue;

				$post['post_title'] = self::PLACEHOLDER_SLUG . '-' . $index;
				$post['post_name']  = self::PLACEHOLDER_SLUG . '-' . $index;

				$post['guid'] = $upload['url'];
				$post_id      = wp_insert_attachment( $post, $upload['file'] );
				wp_update_attachment_metadata(
					$post_id,
					wp_generate_attachment_metadata( $post_id, $upload['file'] )
				);

				$this->placeholder_ids[] = $post_id;
			}
		}
		
		return $this->placeholder_ids;
	}

	public function process_attachment( $post, $url ) {
		if ( is_null( $this->placeholder_image_id ) ) {
			$post_id = $this->_get_placeholder_from_media();
			if ( $post_id ) {
				$this->placeholder_image_id = $post_id;
				return $this->placeholder_image_id;
			}
			
			if ( ! function_exists( 'WP_Filesystem' ) )
				require_once ABSPATH . 'wp-admin/includes/file.php';
			
			global $wp_filesystem;
			
			WP_Filesystem();
			$upload = wp_upload_bits(
				'pojo-placeholder.png',
				null,
				$wp_filesystem->get_contents( POJO_IMPORTER_ASSETS_PATH . 'images/placeholder.png' )
			);

			$info = wp_check_filetype( $upload['file'] );
			if ( $info )
				$post['post_mime_type'] = $info['type'];
			else
				return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'pojo-importer' ) );
			
			$post['post_title'] = self::PLACEHOLDER_SLUG;
			$post['post_name'] = self::PLACEHOLDER_SLUG;
			
			$post['guid'] = $upload['url'];
			$post_id      = wp_insert_attachment( $post, $upload['file'] );
			wp_update_attachment_metadata(
				$post_id,
				wp_generate_attachment_metadata( $post_id, $upload['file'] )
			);

			$this->placeholder_image_id = $post_id;
		}
		
		return $this->placeholder_image_id;
	}

	public function reset_galleries_placeholders() {
		global $wpdb;

		$placeholder_ids = $this->generate_placeholders();
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
	}
	
	public function reset_slideshows_placeholders() {
		global $wpdb;

		$slides = $wpdb->get_col(
			"SELECT `meta_key` FROM `{$wpdb->postmeta}`
				WHERE `meta_key` REGEXP 'slide_slides\\\\[.+\\\\]\\\\[image\\\\]';"
		);
		
		if ( empty( $slides ) ) {
			return;
		}

		$placeholder_ids = $this->generate_placeholders();
		if ( empty( $placeholder_ids ) ) {
			return;
		}

		foreach ( $slides as $slide_meta_key ) {
			shuffle( $placeholder_ids );
			$placeholder_id = $placeholder_ids[0];
			
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_value' => $placeholder_id ),
				array( 'meta_key' => $slide_meta_key )
			);
		}
	}
}
