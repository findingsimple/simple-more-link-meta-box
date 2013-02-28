<?php
/*
Plugin Name: Simple More Link Meta Box
Plugin URI: http://plugins.findingsimple.com
Description: Adds a meta box for customising the more link text (because sometimes flipping to the html view is just too hard and you don't want to edit the theme)
Version: 1.0
Author: Finding Simple ( Jason Conroy & Brent Shepherd )
Author URI: http://findingsimple.com
License: GPL2
*/
/*
Copyright 2012  Finding Simple  (email : plugins@findingsimple.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'Simple_More_Link_Meta_Box' ) ) :

/**
 * So that themes and other plugins can customise the text domain, the Simple_More_Link_Meta_Box
 * should not be initialized until after the plugins_loaded and after_setup_theme hooks.
 * However, it also needs to run early on the init hook.
 *
 * @author Jason Conroy <jason@findingsimple.com>
 * @package Simple More Link Meta Box
 * @since 1.0
 */
function initialize_more_link_meta_box(){
	Simple_More_Link_Meta_Box::init();
}
add_action( 'init', 'initialize_more_link_meta_box', -1 );

/**
 * Plugin Main Class.
 *
 * @package Simple More Link Meta Box
 * @author Jason Conroy <jason@findingsimple.com>
 * @since 1.0
 */
class Simple_More_Link_Meta_Box {

	static $text_domain;

	/**
	 * Initialise
	 */
	public static function init() {
	
		global $wp_version;

		self::$text_domain = apply_filters( 'simple_mlmb_text_domain', 'Simple_MLMB' );
		
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( __CLASS__, 'simple_mlmb_add_meta_box' ) );

		/* Save the meta boxes data on the 'save_post' hook. */
		add_action( 'save_post', array( __CLASS__, 'simple_mlmb_save' ) , 10, 2 );
		
		/* Filter the More link */
		add_filter('the_content_more_link', array( __CLASS__, 'simple_mlmb_filter_link' ) );

	}

	/* Adds custom meta boxes to the theme settings page. */
	public static function simple_mlmb_add_meta_box() {

		/* Add a custom meta box. */
		add_meta_box(
			'simple-mlmb-meta-box',			
			__( 'More Link Text', self::$text_domain  ),
			array( __CLASS__, 'simple_mlmb_meta_box_display' ),			
			'post',
			'normal',
			'low'
		);
		
	}


	/**
	 * Displays the More Link meta box.
	 *
	 */
	public static function simple_mlmb_meta_box_display( $object, $box ) {
		
		wp_nonce_field( basename( __FILE__ ), 'simple-mlmb-nonce' );
								
		$more_link_text = esc_attr( get_post_meta( $object->ID, '_simple_mlmb_link_text' , true) ); 
		
		$remove_scroll = get_post_meta( $object->ID , '_simple_mlmb_remove_scroll' , true);
		
		$remove_scroll = ( !empty( $remove_scroll ) ) ? esc_attr( $remove_scroll ) : 'no' ;
																					
	?>		

		<p>
			<label for="more-link-text"><?php _e( 'More Link Text:', self::$text_domain ); ?></label>
			<br />
			<input name='more-link-text' id='more-link-text' value='<?php echo $more_link_text ?>' class="widefat" />	
			<br />
			<span style="color:#aaa;">Set custom more link text. If blank the default more link text set by WP or your theme will be used</span>
		</p>
		
		<p>
			<label for="remove-scroll"><?php _e( 'Prevent Page Scroll:', self::$text_domain ); ?></label>
			<br />
			<select name='remove-scroll' id='remove-scroll'>
				<option value="yes" <?php selected( $remove_scroll, 'yes' ); ?> >yes</option>
				<option value="no" <?php selected( $remove_scroll, 'no' ); ?> >no</option>
			</select>	
			<br />
			<span style="color:#aaa;">By default, clicking the .more-link anchor opens the web document and scrolls the page to section of the document containing the named anchor (#more-000). Select Yes to prevent this scroll effect.</span>
		</p>
						
	<?php
		}	


	/**
	 * Saves the more link meta box settings as post metadata.
	 *
	 */
	public static function simple_mlmb_save( $post_id, $post ) {

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['simple-mlmb-nonce'] ) || !wp_verify_nonce( $_POST['simple-mlmb-nonce'], basename( __FILE__ ) ) )
			return $post_id;

		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;

		$meta = array(
			'_simple_mlmb_link_text' => strip_tags( $_POST['more-link-text'] ),
			'_simple_mlmb_remove_scroll' => strip_tags( $_POST['remove-scroll'] )
		);
		
		foreach ( $meta as $meta_key => $new_meta_value ) {

			/* Get the meta value of the custom field key. */
			$meta_value = get_post_meta( $post_id, $meta_key, true );

			/* If a new meta value was added and there was no previous value, add it. */
			if ( $new_meta_value && '' == $meta_value )
				add_post_meta( $post_id, $meta_key, $new_meta_value, true );

			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				update_post_meta( $post_id, $meta_key, $new_meta_value );

			/* If there is no new meta value but an old value exists, delete it. */
			elseif ( '' == $new_meta_value && $meta_value )
				delete_post_meta( $post_id, $meta_key, $meta_value );
		}
		
	}

	/**
	 * Filter 'the_content_more_link' to apply custom more link text.
	 *
	 */
	public static function simple_mlmb_filter_link( $more_link ) {
	
		global $post;
		
		$more_link_text = esc_attr( get_post_meta( $post->ID, '_simple_mlmb_link_text' , true) );
		
		if ( !empty( $more_link_text ) )
 			$more_link = preg_replace( '/<a(.+?)>.+?<\/a>/i', "<a$1>$more_link_text</a>" , $more_link );
 
 		$remove_scroll = get_post_meta( $post->ID, '_simple_mlmb_remove_scroll' , true) ;
		
 		if ( $remove_scroll == 'yes' )
 			$more_link = preg_replace( '|#more-[0-9]+|', '', $more_link );
 
		return $more_link;
		
	}	
	
}

endif;