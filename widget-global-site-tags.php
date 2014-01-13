<?php
/*
Plugin Name: Global Site Tags Widget
Plugin URI: http://premium.wpmudev.org/project/global-site-tags
Description: This powerful plugin allows you to simply display a global tag cloud for your entire WordPress Multisite network. How cool is that!
Author: Incsub
Version: 3.0.2
Author URI: http://premium.wpmudev.org
*/

$widget_global_site_tags_title = __('Global Site Tags', "globalsitetags");
$widget_global_site_tags_nice_title = 'global_site_tags';
$widget_global_site_tags_description = __('Displays tags from all blogs', "globalsitetags");
$widget_global_site_tags_height = 600;
$widget_global_site_tags_width = 300;

class widget_global_site_tags extends WP_Widget {

	//Declares the widget_global_site_tags class.
	function widget_global_site_tags() {
		global $widget_global_site_tags_title, $widget_global_site_tags_nice_title, $widget_global_site_tags_description, $widget_global_site_tags_height, $widget_global_site_tags_width;

		// Load the text-domain
		load_plugin_textdomain( 'globalsitetags', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		$widget_ops = array( 'classname' => 'widget_global_site_tags', 'description' => __( $widget_global_site_tags_description ) );
		$control_ops = array( 'width' => $widget_global_site_tags_width, 'height' => $widget_global_site_tags_height );
		$this->WP_Widget( $widget_global_site_tags_nice_title, __( $widget_global_site_tags_title ), $widget_ops, $control_ops );
	}

	//Displays the Widget
	function widget( $args, $instance ) {
		global $globalsitetags;

		extract( $args );
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '&nbsp;' : $instance['title'] );
		$lineOne = empty( $instance['lineOne'] ) ? 'Hello' : $instance['lineOne'];
		$lineTwo = empty( $instance['lineTwo'] ) ? 'World' : $instance['lineTwo'];

		//Before the widget
		echo $before_widget;

		//The title
		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		//Make the Hello World Example widget
		//echo '<div style="text-align:center;padding:10px;">' . $lineOne . '<br />' . $lineTwo . "</div>";
		echo $globalsitetags->global_site_tags_tag_cloud( '', $instance['number'], $instance['tag_cloud_order'], $instance['low_font_size'], $instance['high_font_size'], '', '', $instance['poststype'] );

		//After the widget
		echo $after_widget;
	}

	//Saves the widgets settings.
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( stripslashes( $new_instance['title'] ) );
		$instance['tag_cloud_order'] = strip_tags( stripslashes( $new_instance['tag_cloud_order'] ) );
		$instance['number'] = strip_tags( stripslashes( $new_instance['number'] ) );
		$instance['high_font_size'] = strip_tags( stripslashes( $new_instance['high_font_size'] ) );
		$instance['low_font_size'] = strip_tags( stripslashes( $new_instance['low_font_size'] ) );
		$instance['poststype'] = strip_tags( stripslashes( $new_instance['poststype'] ) );

		return $instance;
	}

	//Creates the edit form for the widget.
	function form( $instance ) {
		//Defaults
		$post_types = $this->get_post_types();
		$instance = wp_parse_args( (array)$instance, array(
			'title'           => '',
			'tag_cloud_order' => 'count',
			'number'          => 25,
			'high_font_size'  => 52,
			'low_font_size'   => 14,
			'poststype'       => 'post',
		) );

		//Output the options
		?><p>
			<label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Widget Title', 'globalsitetags' ) ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'title' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'title' ) ?>" value="<?php echo esc_attr( $instance['title'] ) ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'tag_cloud_order' ) ?>"><?php _e( 'Tag Cloud Order', 'globalsitetags' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'tag_cloud_order' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'tag_cloud_order' ) ?>">
				<option value="count"><?php _e( 'Tag Count', "globalsitetags" ) ?></option>
				<option value="most_recent" <?php selected( 'most_recent', $instance['tag_cloud_order'] ) ?>><?php _e( 'Most Recent', 'globalsitetags' ) ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'number' ) ?>"><?php _e( 'Number of Tags', 'globalsitetags' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'number' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'number' ) ?>">
				<?php for ( $counter = 1; $counter <= 100; $counter++ ) : ?>
				<option value="<?php echo $counter ?>"<?php selected( $counter, $instance['number'] ) ?>><?php echo $counter ?></option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'high_font_size' ) ?>"><?php _e( 'Largest Font Size', 'globalsitetags' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'high_font_size' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'high_font_size' ) ?>">
				<?php for ( $counter = 1; $counter <= 100; $counter++ ) : ?>
				<option value="<?php echo $counter ?>"<?php selected( $counter, $instance['high_font_size'] ) ?>><?php echo $counter ?>px</option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'low_font_size' ) ?>"><?php _e( 'Smallest Font Size', 'globalsitetags' ) ?>:</label>
			<select id="<?php echo $this->get_field_id( 'low_font_size' ) ?>" class="widefat" name="<?php echo $this->get_field_name( 'low_font_size' ) ?>">
				<?php for ( $counter = 1; $counter <= 100; $counter++ ) : ?>
				<option value="<?php echo $counter ?>"<?php selected( $counter, $instance['low_font_size'] ) ?> ><?php echo $counter ?>px</option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'poststype' ) ?>"><?php _e( 'Post Type', 'globalsitetags' ) ?>:</label>
			<select name="<?php echo $this->get_field_name( 'poststype' ) ?>" id="<?php echo $this->get_field_id( 'poststype' ); ?>" class="widefat">
				<option value="all"><?php _e( 'all', 'globalsitetags' ) ?></option>
				<?php if ( !empty( $post_types ) ) : ?>
					<?php foreach ( $post_types as $r ) : ?>
					<option value="<?php echo esc_attr( $r ) ?>"<?php selected( $r, $instance['poststype'] ) ?>><?php echo esc_html( $r ) ?></option>
					<?php endforeach; ?>
				<?php else : ?>
					<option value="post"<?php selected( $instance['poststype'], 'post' ) ?>><?php _e( 'post', 'globalsitetags' ) ?></option>
				<?php endif; ?>
			</select>
		</p><?php
	}

	function get_post_types() {
		global $wpdb;
		return (array)$wpdb->get_col( "SELECT post_type FROM {$wpdb->base_prefix}network_posts GROUP BY post_type" );
	}

}

add_action( 'widgets_init', 'widget_global_site_tags_init' );
function widget_global_site_tags_init() {
	register_widget( 'widget_global_site_tags' );
}