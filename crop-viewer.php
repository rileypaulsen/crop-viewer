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
 */


if( !class_exists('CropViewer') ){
	class CropViewer {

		const CROP_VIEWER = 'crop_view';
		const CROP_VIEWER_TYPES_OPTION_KEY = 'crop_view_post_types';
		const CROP_VIEWER_SIZES_OPTION_KEY = 'crop_view_sizes';
		const FULL_SIZE_KEY = 'full'; //the key with which to grab the original-size image using WP's attachment functions

		private $original_content_width;
		private $post_types;
		private $sizes;
		private $selected_post_types;
		private $selected_sizes;

		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
		}

		/**
		* Sets up WP Hooks
		*/
		public function init(){
			add_action( 'add_meta_boxes', array($this,'add_meta_box') );
			add_action( 'admin_init', array( $this, 'admin_setup' ) );
		}

		/**
		* Sets up the plugin on the admin side
		*/
		public function admin_setup(){
			$this->post_types = get_post_types();
			$this->selected_post_types = get_option(self::CROP_VIEWER_TYPES_OPTION_KEY);
			$this->sizes = get_intermediate_image_sizes();
			$this->selected_sizes = get_option(self::CROP_VIEWER_SIZES_OPTION_KEY);

			if( empty($this->selected_post_types) ){
				$this->selected_post_types = array();
			}

			if( empty($this->selected_sizes) ){
				$this->selected_sizes = array();
			}

			register_setting( 'media', self::CROP_VIEWER_TYPES_OPTION_KEY, array($this,'sanitize_selected_post_types') );
			register_setting( 'media', self::CROP_VIEWER_SIZES_OPTION_KEY, array($this,'sanitize_selected_sizes') );
			add_settings_section(self::CROP_VIEWER.'_section', 'Preview Featured Image Crops', array($this,'section_html'), 'media');
			add_settings_field( self::CROP_VIEWER_TYPES_OPTION_KEY, 'Post Types' , array( $this, 'settings_field_types_html' ) , 'media', self::CROP_VIEWER.'_section' );
			add_settings_field( self::CROP_VIEWER_SIZES_OPTION_KEY, 'Sizes to Preview' , array( $this, 'settings_field_sizes_html' ) , 'media', self::CROP_VIEWER.'_section' );
		}

		/**
		* Adds settings instructions
		*/
		public function section_html($arg){
			echo '<p>The selected post types will display a featured image crop preview on the edit screen of each post. <em>Note: Only post types with support for Featured Image (&ldquo;thumbnail&rdquo;) will be displayed below.</em></p>';
		}

		/**
		* Generates a list of checkboxes for the available post types
		*/
		public function settings_field_types_html($args){
			echo '<ul>';
			foreach ($this->post_types as $type) {
				if( post_type_supports($type,'thumbnail') ){
					echo '<li><label><input type="checkbox" '.checked(true, in_array($type, $this->selected_post_types), false).' value="'.$type.'" name="'.self::CROP_VIEWER_TYPES_OPTION_KEY.'[]" /> '.$type.'</label></li>';
				}
			}
			echo '</ul>';
		}

		/**
		* Generates a list of checkboxes for the available image sizes
		*/
		public function settings_field_sizes_html($args){
			echo '<ul>';
			echo '<li><label><input type="checkbox" '.checked(true, in_array(self::FULL_SIZE_KEY, $this->selected_sizes), false).' value="full" name="'.self::CROP_VIEWER_SIZES_OPTION_KEY.'[]" /> full (original upload size)</label></li>';
			foreach ($this->sizes as $size) {
				echo '<li><label><input type="checkbox" '.checked(true, in_array($size, $this->selected_sizes), false).' value="'.$size.'" name="'.self::CROP_VIEWER_SIZES_OPTION_KEY.'[]" /> '.$size.'</label></li>';
			}
			echo '</ul>';
		}

		/**
		* Restricts the selected post types to registered post types
		*/
		public function sanitize_selected_post_types($types){
			if( !is_array($types) ){
				return null;
			}
			return array_filter($types, function($type){
				return in_array($type, $this->post_types);
			});
		}

		/**
		* Restricts the selected sizes to those registered with add_image_size(), the built-in WP media sizes, or the full size image
		*/
		public function sanitize_selected_sizes($sizes){
			if( !is_array($sizes) ){
				return null;
			}
			return array_filter($sizes, function($size){
				return ( in_array($size, $this->sizes) || $size == self::FULL_SIZE_KEY);
			});
		}

		/**
		* Sets up meta boxes at the bottom of the sidebar for desired post types
		*/
		public function add_meta_box(){
			foreach ( $this->selected_post_types as $type ) {
				add_meta_box(
					'crop-viewer',
					'Featured Image Crops',
					array($this,'meta_box_html'),
					$type,
					'side',
					'low'
				);
			}
		}

		/**
		* Outputs a meta box containing previews of the desired image sizes
		*/
		public function meta_box_html($post){
			if( !has_post_thumbnail( $post->ID ) ){
				echo '<p class="empty"><em>Set the Featured Image for this post to preview the auto-generated crop sizes.</em></p>';
				return;
			}

			$this->remove_content_width();

			$thumbnailID = get_post_thumbnail_id( $post->ID );
			$sizes = $this->get_image_sizes();
			echo '<style type="text/css">';
				echo '#crop-viewer .inside li + li { margin-top:20px; padding-top:20px; border-top:1px solid #eee; }';
				echo '#crop-viewer .inside h4, #crop-viewer .inside h5 { text-align:center; margin-top:0; }';
				echo '#crop-viewer .inside h5:first-of-type:not(:last-of-type) { margin-bottom:0; }';
				echo '#crop-viewer .inside span { font-weight:300; }';
				echo '#crop-viewer .inside img { max-width:100%; height:auto; }';
			echo '</style>';
			echo '<ul>';
				//need a special condition for 'full' size, since it's not returned in $this->get_image_sizes() via get_intermediate_image_sizes()
				if( in_array(self::FULL_SIZE_KEY,$this->selected_sizes) ){
					$source = wp_get_attachment_image_src( $thumbnailID, self::FULL_SIZE_KEY );
					echo '<li>';
						echo '<h4>&ldquo;Original&rdquo;</h4>';
						echo '<h5>Actual Size: <span>'.$source[1].' x '.$source[2].'</span></h5>';
						echo '<a target="_blank" href="'.$source[0].'"><img src="'.$source[0].'" /></a>';
					echo '</li>';
				}
			foreach( $sizes as $size => $details ){
				if( !in_array($size, $this->selected_sizes) ){
					continue;
				}
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

		/**
		* Gets all available image size names and their details as registered by add_image_size() or the media settings page
		* Straight from the codex -- https://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes#Examples
		*/
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

		/**
		* Eliminate the $content_width sizing on demand to allow the full dimensions of a crop to be displayed in the meta box
		* This clears out the strange theme variable that limits image dimensions returned in wp_get_attachment_image_src
		* $content_width typically ensures that a theme doesn't get back dimensions for an <img> tag that are larger than its content area
		*/
		private function remove_content_width(){
			global $content_width;

			if( !empty( $content_width ) ){
				$this->original_content_width = $content_width;
				$content_width = null;
			}
		}

		/**
		* Restores the initial content width, if there was one
		*/
		private function restore_content_width(){
			global $content_width;

			if( !empty($this->original_content_width) ){
				$content_width = $this->original_content_width;
			}
		}

	}

	new CropViewer();
}