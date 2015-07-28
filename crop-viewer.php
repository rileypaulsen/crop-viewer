<?php
/**
 * Plugin Name:	   Crop Viewer
 * Plugin URI:		http://rileypaulsen.com
 * Description:	   Displays a metabox of the various crop sizes for a post's featured image in the admin editor.
 * Version:		   1.0.0
 * Author:			Riley Paulsen
 * Author URI:		http://rileypaulsen.com
 * License:		   GPL-2.0+
 * License URI:	   http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:	   crop-viewer
 */


if( !class_exists('CropViewer') ){
	class CropViewer {

		public $original_content_width;

		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
		}

		public function init(){
			add_action( 'add_meta_boxes', array($this,'add_meta_box') );
		}

		public function add_meta_box(){
			$types = array( 'post', 'page' );

			foreach ( $types as $type ) {
				add_meta_box(
					'crop-viewer',
					'Featured Image Crops',
					array($this,'meta_box_html'),
					$type,
					'side'
				);
			}
		}

		public function meta_box_html($post){
			if( !has_post_thumbnail( $post->ID ) ){
				return;
			}

			$this->remove_content_width();

			$thumbnailID = get_post_thumbnail_id( $post->ID );
			$sizes = $this->get_image_sizes();
			echo '<style type="text/css">';
				echo '#crop-viewer li + li { margin-top:20px; padding-top:20px; border-top:1px solid #CCC; }';
				echo '#crop-viewer h4, #crop-viewer h5 { text-align:center; margin-top:0; }';
				echo '#crop-viewer h5:first-of-type:not(:last-of-type) { margin-bottom:0; }';
				echo '#crop-viewer span { font-weight:300; }';
				echo '#crop-viewer img { max-width:100%; height:auto; }';
			echo '</style>';
			echo '<ul>';
				$source = wp_get_attachment_image_src( $thumbnailID, 'full' );
				echo '<li>';
					echo '<h4>&ldquo;Original&rdquo;</h4>';
					echo '<h5>Actual Size: <span>'.$source[1].' x '.$source[2].'</span></h5>';
					echo '<a target="_blank" href="'.$source[0].'"><img src="'.$source[0].'" /></a>';
				echo '</li>';
			foreach( $sizes as $size => $details ){
				$cropped = ( $details['crop'] ) ? 'Yes' : 'No';
				$source = wp_get_attachment_image_src( $thumbnailID, $size );
				echo '<li>';
					echo '<h4>&ldquo;'.$size.'&rdquo;</h4>';
					echo '<h5>Desired Size: <span>'.$details['width'].' x '.$details['height'].'</span> | Cropped: <span>'.$cropped.'</span></h5>';
					echo '<h5>Actual Size: <span>'.$source[1].' x '.$source[2].'</span></h5>';
					echo '<a target="_blank" href="'.$source[0].'"><img src="'.$source[0].'" /></a>';
				echo '</li>';
			}
			echo '</ul>';

			$this->restore_content_width();

		}

		//straight from the codex -- https://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes#Examples
		function get_image_sizes( $size = '' ) {
			global $_wp_additional_image_sizes;

			$sizes = array();
			$get_intermediate_image_sizes = get_intermediate_image_sizes();

			// Create the full array with sizes and crop info
			foreach( $get_intermediate_image_sizes as $_size ) {
				if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {
					$sizes[ $_size ]['width'] = get_option( $_size . '_size_w' );
					$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
					$sizes[ $_size ]['crop'] = (bool) get_option( $_size . '_crop' );
				} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
					$sizes[ $_size ] = array(
						'width' => $_wp_additional_image_sizes[ $_size ]['width'],
						'height' => $_wp_additional_image_sizes[ $_size ]['height'],
						'crop' =>  $_wp_additional_image_sizes[ $_size ]['crop']
					);
				}
			}

			// Get only 1 size if found
			if ( $size ) {
				if( isset( $sizes[ $size ] ) ) {
					return $sizes[ $size ];
				} else {
					return false;
				}
			}
			return $sizes;
		}

		private function remove_content_width(){
			//WP has this weird theme variable to limit the image dimensions returned in wp_get_attachment_image_src
			//to ensure that a theme doesn't get back dimensions for an <img> tag that are larger than its content area
			//let's disable that during display, and then change it back;
			global $content_width;

			if( !empty( $content_width ) ){
				$this->original_content_width = $content_width;
				$content_width = 10000; //something enormous to prevent dimension limiting
			}
		}

		private function restore_content_width(){
			global $content_width;

			if( !empty($this->original_content_width) ){
				$content_width = $this->original_content_width;
			}
		}

	}

	new CropViewer();
}