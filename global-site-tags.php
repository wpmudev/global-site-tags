<?php
/*
Plugin Name: Global Site Tags
Plugin URI: http://premium.wpmudev.org/project/global-site-tags
Description: This powerful plugin allows you to simply display a global tag cloud for your entire WordPress Multisite network. How cool is that!
Author: Barry (Incsub)
Version: 3.0.2
Author URI: http://premium.wpmudev.org
WDP ID: 105
*/

/*
Copyright 2013 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class globalsitetags {

	var $build = 4;

	var $db;

	var $global_site_tags_base = 'tags'; //domain.tld/BASE/ Ex: domain.tld/tags/

	function __construct() {

		global $wpdb, $current_site, $current_blog;

		// Get a local handle to the database
		$this->db =& $wpdb;

		add_action( 'plugins_loaded', array( &$this, 'global_site_tags_internationalisation' ) );

		if ($current_blog->domain . $current_blog->path == $current_site->domain . $current_site->path){

			if( get_option('gst_installed', 0) < $this->build || get_option('gst_installed', 0) == 'yes' ) {
				add_action('init', array( &$this, 'initialise_plugin') );
			}

			// Add the rewrites
			add_action('generate_rewrite_rules', array(&$this, 'add_rewrite'));
			add_filter('query_vars', array(&$this, 'add_queryvars'));

			add_filter('the_content', array( &$this, 'global_site_tags_output' ), 20 );
			add_filter('the_title', array( &$this, 'global_site_tags_title_output' ) , 99, 2);

		}

		add_action('wpmu_options', array( &$this, 'global_site_tags_site_admin_options' ) );
		add_action('update_wpmu_options', array( &$this, 'global_site_tags_site_admin_options_process' ) );

	}

	function globalsitetags() {
		$this->__construct();
	}

	function initialise_plugin() {
		// Flush the rules to add our pages
		$this->global_site_tags_flush_rules();
		// Add the default tag page
		$this->global_site_tags_page_setup();
		// Set the option to say we are installed
		update_option('gst_installed', $this->build);
	}

	function global_site_tags_flush_rules() {
    	global $wp_rewrite;

        $wp_rewrite->flush_rules();
	}

	function add_queryvars($vars) {
		// This function add the namespace (if it hasn't already been added) and the
		// eventperiod queryvars to the list that WordPress is looking for.
		// Note: Namespace provides a means to do a quick check to see if we should be doing anything

		if(!in_array('namespace',$vars)) $vars[] = 'namespace';
		if(!in_array('tag',$vars)) $vars[] = 'tag';
		if(!in_array('paged',$vars)) $vars[] = 'paged';
		if(!in_array('type',$vars)) $vars[] = 'type';

		return $vars;
	}

	function add_rewrite( $wp_rewrite ) {

		// This function adds in the api rewrite rules
		// Note the addition of the namespace variable so that we know these are vent based
		// calls
		$new_rules = array();

		$new_rules[$this->global_site_tags_base . '/(.+)/page/?([0-9]{1,})'] = 'index.php?namespace=gst&tag=' . $wp_rewrite->preg_index(1) . '&paged=' . $wp_rewrite->preg_index(2) . '&type=tag&pagename=' . $this->global_site_tags_base;
		$new_rules[$this->global_site_tags_base . '/(.+)'] = 'index.php?namespace=gst&tag=' . $wp_rewrite->preg_index(1) . '&type=tag&pagename=' . $this->global_site_tags_base;
		$new_rules[$this->global_site_tags_base . ''] = 'index.php?namespace=gst&type=tag&pagename=' . $this->global_site_tags_base;

		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

		return $wp_rewrite;

	}

	function global_site_tags_internationalisation() {
		// Load the text-domain
		$locale = apply_filters( 'globalsitetags_locale', get_locale() );
		$mofile = plugin_basename( dirname(__FILE__) . "/languages/globalsitetags-$locale.mo");

		if ( file_exists( $mofile ) )
			load_plugin_textdomain( 'globalsitetags', false, $mofile );
	}

	function global_site_tags_page_setup() {
		global $wpdb, $user_ID;

		if ( get_site_option('global_site_tags_page_setup') != 'complete' && is_super_admin() ) {

			$page_id = get_site_option('global_site_tags_page');
			if(empty($page_id)) {
				// a page hasn't been set - so check if there is already one with the base name
				$page_id = $this->db->get_var("SELECT ID FROM {$this->db->posts} WHERE post_name = '" . $this->global_site_tags_base . "' AND post_type = 'page'");
				if ( empty( $page_id ) ) {
					// Doesn't exist so create the page
					$post = array(	"post_author"		=>	$user_ID,
									"post_date"			=>	current_time( 'mysql' ),
									"post_date_gmt"		=>	current_time( 'mysql' ),
									"post_content"		=>	'',
									"post_title"		=>	__('Tags', 'globalsitetags'),
									"post_excerpt"		=>	'',
									"post_status"		=>	'publish',
									"comment_status"	=>	'closed',
									"ping_status"		=>	'closed',
									"post_password"		=>	'',
									"post_name"			=>	$this->global_site_tags_base,
									"to_ping"			=>	'',
									"pinged"			=>	'',
									"post_modified"		=>	current_time( 'mysql' ),
									"post_modified_gmt"	=>	current_time( 'mysql' ),
									"post_content_filtered"	=>	'',
									"post_parent"			=>	0,
									"menu_order"			=>	0,
									"post_type"				=>	'page',
									"comment_count"			=>	0
								);
					$page_id = wp_insert_post( $post );

				}
				update_site_option( 'global_site_tags_page', $page_id );
			}

			update_site_option('global_site_tags_page_setup', 'complete');
		}

	}

	function global_site_tags_site_admin_options() {

		$global_site_tags_per_page = get_site_option('global_site_tags_per_page', '10');
		$global_site_tags_shown = get_site_option('global_site_tags_shown', '50');
		$global_site_tags_background_color = get_site_option('global_site_tags_background_color', '#F2F2EA');
		$global_site_tags_alternate_background_color = get_site_option('global_site_tags_alternate_background_color', '#FFFFFF');
		$global_site_tags_border_color = get_site_option('global_site_tags_border_color', '#CFD0CB');
		$global_site_tags_banned_tags = get_site_option('global_site_tags_banned_tags', 'uncategorized');
		$global_site_tags_tag_cloud_order = get_site_option('global_site_tags_tag_cloud_order', 'count');

		$global_site_tags_post_type = get_site_option('global_site_tags_post_type', 'post');

		$global_site_tags_get_taxonomies = get_site_option('global_site_tags_get_taxonomies', 'post_tag');

		?>
			<h3><?php _e('Site Tags', "globalsitetags") ?></h3>
			<table class="form-table">
				<tr valign="top">
	                <th width="33%" scope="row"><?php _e('Tags Shown', "globalsitetags") ?></th>
	                <td>
					<select name="global_site_tags_shown" id="global_site_tags_shown">
					   <option value="5" <?php if ( $global_site_tags_shown == '5' ) { echo 'selected="selected"'; } ?> ><?php _e('5', "globalsitetags"); ?></option>
					   <option value="10" <?php if ( $global_site_tags_shown == '10' ) { echo 'selected="selected"'; } ?> ><?php _e('10', "globalsitetags"); ?></option>
					   <option value="15" <?php if ( $global_site_tags_shown == '15' ) { echo 'selected="selected"'; } ?> ><?php _e('15', "globalsitetags"); ?></option>
					   <option value="20" <?php if ( $global_site_tags_shown == '20' ) { echo 'selected="selected"'; } ?> ><?php _e('20', "globalsitetags"); ?></option>
					   <option value="25" <?php if ( $global_site_tags_shown == '25' ) { echo 'selected="selected"'; } ?> ><?php _e('25', "globalsitetags"); ?></option>
					   <option value="30" <?php if ( $global_site_tags_shown == '30' ) { echo 'selected="selected"'; } ?> ><?php _e('30', "globalsitetags"); ?></option>
					   <option value="35" <?php if ( $global_site_tags_shown == '35' ) { echo 'selected="selected"'; } ?> ><?php _e('35', "globalsitetags"); ?></option>
					   <option value="40" <?php if ( $global_site_tags_shown == '40' ) { echo 'selected="selected"'; } ?> ><?php _e('40', "globalsitetags"); ?></option>
					   <option value="45" <?php if ( $global_site_tags_shown == '45' ) { echo 'selected="selected"'; } ?> ><?php _e('45', "globalsitetags"); ?></option>
					   <option value="50" <?php if ( $global_site_tags_shown == '50' ) { echo 'selected="selected"'; } ?> ><?php _e('50', "globalsitetags"); ?></option>
					</select>
	                <br /><?php //_e('') ?></td>
	            </tr>
	            <tr valign="top">
	                <th width="33%" scope="row"><?php _e('Listing Per Page', "globalsitetags") ?></th>
	                <td>
					<select name="global_site_tags_per_page" id="global_site_tags_per_page">
					   <option value="5" <?php if ( $global_site_tags_per_page == '5' ) { echo 'selected="selected"'; } ?> ><?php _e('5', "globalsitetags"); ?></option>
					   <option value="10" <?php if ( $global_site_tags_per_page == '10' ) { echo 'selected="selected"'; } ?> ><?php _e('10', "globalsitetags"); ?></option>
					   <option value="15" <?php if ( $global_site_tags_per_page == '15' ) { echo 'selected="selected"'; } ?> ><?php _e('15', "globalsitetags"); ?></option>
					   <option value="20" <?php if ( $global_site_tags_per_page == '20' ) { echo 'selected="selected"'; } ?> ><?php _e('20', "globalsitetags"); ?></option>
					   <option value="25" <?php if ( $global_site_tags_per_page == '25' ) { echo 'selected="selected"'; } ?> ><?php _e('25', "globalsitetags"); ?></option>
					   <option value="30" <?php if ( $global_site_tags_per_page == '30' ) { echo 'selected="selected"'; } ?> ><?php _e('30', "globalsitetags"); ?></option>
					   <option value="35" <?php if ( $global_site_tags_per_page == '35' ) { echo 'selected="selected"'; } ?> ><?php _e('35', "globalsitetags"); ?></option>
					   <option value="40" <?php if ( $global_site_tags_per_page == '40' ) { echo 'selected="selected"'; } ?> ><?php _e('40', "globalsitetags"); ?></option>
					   <option value="45" <?php if ( $global_site_tags_per_page == '45' ) { echo 'selected="selected"'; } ?> ><?php _e('45', "globalsitetags"); ?></option>
					   <option value="50" <?php if ( $global_site_tags_per_page == '50' ) { echo 'selected="selected"'; } ?> ><?php _e('50', "globalsitetags"); ?></option>
					</select>
	                <br /><?php //_e('') ?></td>
	            </tr>
	            <tr valign="top">
	                <th width="33%" scope="row"><?php _e('Background Color', "globalsitetags") ?></th>
	                <td><input name="global_site_tags_background_color" type="text" id="global_site_tags_background_color" value="<?php echo $global_site_tags_background_color; ?>" size="20" />
	                <br /><?php _e('Default', "globalsitetags") ?>: #F2F2EA</td>
	            </tr>
	            <tr valign="top">
	                <th width="33%" scope="row"><?php _e('Alternate Background Color', "globalsitetags") ?></th>
	                <td><input name="global_site_tags_alternate_background_color" type="text" id="global_site_tags_alternate_background_color" value="<?php echo $global_site_tags_alternate_background_color; ?>" size="20" />
	                <br /><?php _e('Default', "globalsitetags") ?>: #FFFFFF</td>
	            </tr>
	            <tr valign="top">
	                <th width="33%" scope="row"><?php _e('Border Color', "globalsitetags") ?></th>
	                <td><input name="global_site_tags_border_color" type="text" id="global_site_tags_border_color" value="<?php echo $global_site_tags_border_color; ?>" size="20" />
	                <br /><?php _e('Default', "globalsitetags") ?>: #CFD0CB</td>
	            </tr>
	            <tr valign="top">
	                <th width="33%" scope="row"><?php _e('Banned Tags', "globalsitetags") ?></th>
	                <td><input name="global_site_tags_banned_tags" type="text" id="global_site_tags_banned_tags" value="<?php echo $global_site_tags_banned_tags; ?>" style="width: 95%;" />
	                <br /><?php _e('Banned tags will not appear in tag clouds. Please separate tags with commas. Ex: tag1, tag2, tag3', "globalsitetags") ?></td>
	            </tr>
	            <tr valign="top">
	                <th width="33%" scope="row"><?php _e('Tag Cloud Order', "globalsitetags") ?></th>
	                <td>
					<select name="global_site_tags_tag_cloud_order" id="global_site_tags_tag_cloud_order">
					   <option value="count" <?php if ( $global_site_tags_tag_cloud_order == 'count' ) { echo 'selected="selected"'; } ?> ><?php _e('Tag Count', "globalsitetags"); ?></option>
					   <option value="most_recent" <?php if ( $global_site_tags_tag_cloud_order == 'most_recent' ) { echo 'selected="selected"'; } ?> ><?php _e('Most Recent', "globalsitetags"); ?></option>
					</select>
	                <br /><?php //_e('') ?></td>
	            </tr>

				<tr valign="top">
		                <th width="33%" scope="row"><?php _e('List Post Type', 'globalsitetags') ?></th>
		                <td>
						<select name="global_site_tags_post_type" id="global_site_tags_post_type">
						   <option value="all" <?php selected( $global_site_tags_post_type, 'all' ); ?> ><?php _e('all', 'globalsitetags'); ?></option>
							<?php
							//$global_site_tags_get_taxonomies = get_site_option('global_site_tags_get_taxonomies', 'post_tag');

							$post_types = $this->global_site_tags_get_post_types();
							if(!empty($post_types)) {
								foreach($post_types as $r) {
									?>
									<option value="<?php echo $r; ?>" <?php selected( $global_site_tags_post_type, $r ); ?> ><?php _e($r, 'globalsitetags'); ?></option>
									<?php
								}
							}
							?>
						</select></td>
		        </tr>

			</table>
		<?php

	}

	function global_site_tags_get_post_types() {

		$sql = "SELECT post_type FROM " . $this->db->base_prefix . "network_posts GROUP BY post_type";

		$results = $this->db->get_col( $sql );

		return $results;
	}

	function global_site_tags_get_taxonomies() {

		$sql = "SELECT taxonomy FROM " . $this->db->base_prefix . "network_term_taxonomy GROUP BY taxonomy";

		$results = $this->db->get_col( $sql );

		return $results;
	}

	function global_site_tags_site_admin_options_process() {

		update_site_option( 'global_site_tags_shown' , $_POST['global_site_tags_shown']);
		update_site_option( 'global_site_tags_per_page' , $_POST['global_site_tags_per_page']);
		update_site_option( 'global_site_tags_background_color' , trim( $_POST['global_site_tags_background_color'] ));
		update_site_option( 'global_site_tags_alternate_background_color' , trim( $_POST['global_site_tags_alternate_background_color'] ));
		update_site_option( 'global_site_tags_border_color' , trim( $_POST['global_site_tags_border_color'] ));
		update_site_option( 'global_site_tags_banned_tags' , trim( $_POST['global_site_tags_banned_tags'] ));
		update_site_option( 'global_site_tags_tag_cloud_order' , trim( $_POST['global_site_tags_tag_cloud_order'] ));

		update_site_option('global_site_tags_post_type', $_POST['global_site_tags_post_type'] );

	}

	function global_site_tags_tag_cloud( $content, $number, $order_by = '', $low_font_size = 14, $high_font_size = 52, $class, $cloud_banned_tags = false, $global_site_tags_post_type = 'post' ) {

		global $wpdb, $current_site, $global_site_tags_base;

		$global_site_tags_banned_tags = get_site_option('global_site_tags_banned_tags', 'uncategorized');
		$global_site_tags_tag_cloud_order = get_site_option('global_site_tags_tag_cloud_order', 'count');

		$global_site_tags_banned_tags_list = explode( ',', $global_site_tags_banned_tags );
		$global_site_tags_banned_tags_list = array_map( 'trim', $global_site_tags_banned_tags_list );

		if ( is_array( $cloud_banned_tags ) ) {
			$global_site_tags_banned_tags_list = array_merge($cloud_banned_tags, $global_site_tags_banned_tags_list);
		}

		$query = "SELECT count(*) as term_count, t.term_id FROM " . $wpdb->base_prefix . "network_terms as t
		INNER JOIN " . $wpdb->base_prefix . "network_term_taxonomy AS tt ON t.term_id = tt.term_id
		INNER JOIN " . $wpdb->base_prefix . "network_term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
		INNER JOIN " . $wpdb->base_prefix . "network_posts AS np ON np.ID = tr.object_id
		WHERE tt.taxonomy = 'post_tag'";

		if( $global_site_tags_post_type != 'all' ) {

			$query .= " AND np.post_type = '" . $global_site_tags_post_type . "'";

			/*
			SELECT count(*) as term_count, t.term_id FROM wp_network_terms as t
			INNER JOIN wp_network_term_taxonomy AS tt ON t.term_id = tt.term_id
			INNER JOIN wp_network_term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN wp_network_posts AS np ON np.ID = tr.object_id
			WHERE tt.taxonomy = 'post_tag' AND np.post_type = 'post'
			GROUP BY t.term_id
			*/

		}

		$query .= " GROUP BY t.term_id";

		if ( empty($order_by) ) {
			$order_by = $global_site_tags_tag_cloud_order;
		}

		if ($order_by == 'count'){
			$query .= ' ORDER BY term_count DESC ';
		} else if ($order_by == 'most_recent'){
			$query .= ' ORDER BY term_count DESC ';
		}
		$query .= ' LIMIT ' . $number;

		$thetags = $wpdb->get_results( $query );

		if( !empty($thetags) ){
			//insert term names
			$tags_array_add = array();
			$loop_count = 0;

			foreach ($thetags as $tag){
				$loop_count = $loop_count + 1;

				$tagdetails = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->base_prefix . "network_terms WHERE term_id = %d", $tag->term_id ) );
				if(!empty($tagdetails)) {
					$tagname = $tagdetails->name;
					$tagslug = $tagdetails->slug;
				}

				$tags_array_add[$loop_count]['term_name'] = $tagname;
				$tags_array_add[$loop_count]['term_slug'] = $tagslug;

				$tags_array_add[$loop_count]['term_count'] = $tag->term_count;
				$tags_array_add[$loop_count]['term_id'] = $tag->term_id;
			}
			$tags_array = $tags_array_add;

			//get min/max counts
			$term_min_count = 99999999999;
			$term_max_count = 0;
			foreach ($tags_array as $tag){
				$hide_tag = 'false';
				foreach ($global_site_tags_banned_tags_list as $blacklist_tag) {
					if (strtolower($tag['term_name']) == strtolower($blacklist_tag)){
						$hide_tag = 'true';
					}
				}
				if ($hide_tag != 'true'){
					if ($tag['term_count'] > $term_max_count){
						$term_max_count = $tag['term_count'];
					}
					if ($tag['term_count'] < $term_min_count){
						$term_min_count = $tag['term_count'];
					}
				}
			}

			$term_count = count($tags_array);
			//adjust term count
			foreach ($tags_array as $tag){
				foreach ($global_site_tags_banned_tags_list as $blacklist_tag) {
					if (strtolower($tag['term_name']) == strtolower($blacklist_tag)){
						$term_count = $term_count - 1;
					}
				}
			}
			//math fun... heh
			$font_difference = $high_font_size - $low_font_size;
			$term_difference = $term_max_count - $term_min_count;
			$term_difference = $term_difference + 1;
			if ($term_difference > 0){
				$font_unit = $font_difference / $term_difference;
			} else {
				$font_unit = $low_font_size;
			}

			//loop through and toss out the tag cloud
			$counter = 1;

			//print_r($tags_array);
			$content .= '<div>';
			foreach ($tags_array as $tag){
				$hide_tag = 'false';
				foreach ($global_site_tags_banned_tags_list as $blacklist_tag) {
					if (strtolower($tag['term_name']) == strtolower($blacklist_tag)){
						$hide_tag = 'true';
					}
				}
				if ($hide_tag != 'true'){
					//font size
					if ($tag['term_count'] == $term_max_count){
						$font_size = $high_font_size;
					} else if ($tag['term_count'] == $term_min_count){
						$font_size = $low_font_size;
					} else {
						$font_size = $tag['term_count'] * $font_unit;
						$font_size = $font_size + $low_font_size;
					}
					//output
					if ($class != ''){
						$content .= '<a class="' . $class . '" href="http://' . $current_site->domain . $current_site->path . $this->global_site_tags_base . '/' . $tag['term_slug'] . '/" title="' . __('recent post(s)') . '" style="font-size: ' . $font_size . 'px;" id="cat-' . $tag['term_id'] . '">' . $tag['term_name'] . '</a>' . "\n";
					} else {
						$content .= '<a href="http://' . $current_site->domain . $current_site->path . $this->global_site_tags_base . '/' . $tag['term_slug'] . '/" title="' . __('recent post(s)', "globalsitetags") . '" style="float:left;padding-bottom:20px;padding-right:2px;text-decoration:none;font-size: ' . $font_size . 'px;" id="cat-' . $tag['term_id'] . '">' . $tag['term_name'] . '</a>' . "\n";
					}
					$counter = $counter + 1;
				}
			}
			$content .= '</div>';
		} else {
			$content .= '<p><center>' . __("There are no tags to display.", "globalsitetags") . '</center></p>';
		}
		return $content;

	}

	//------------------------------------------------------------------------//
	//---Output Functions-----------------------------------------------------//
	//------------------------------------------------------------------------//

	function global_site_tags_title_output($title, $post_ID = '') {

		global $wpdb, $current_site, $post, $global_site_tags_base;

		global $wp_query;

		if ( isset($wp_query->query_vars['namespace']) && $wp_query->query_vars['namespace'] == 'gst' && $wp_query->query_vars['type'] == 'tag' && !empty( $wp_query->query_vars['tag'] ) ) {

				$page_id = get_site_option('global_site_tags_page');


				if( (!empty($page_id) && $page_id == $post_ID) || (!empty($post) && $post->ID == $post_ID) ) {

					$tag_name = $wpdb->get_var("SELECT name FROM " . $wpdb->base_prefix . "network_terms WHERE slug = '" . urldecode( $wp_query->query_vars['tag'] ) . "'");

					$title = '<a href="http://' . $current_site->domain . $current_site->path . $this->global_site_tags_base . '/">' . $title . '</a> &raquo; ' . '<a href="http://' . $current_site->domain . $current_site->path . $this->global_site_tags_base . '/' . $wp_query->query_vars['tag'] . '/">' . $tag_name . '</a>';

				}
		}

		return $title;

	}

	function global_site_tags_output( $content ) {
		global $wpdb, $current_site, $post, $global_site_tags_base, $members_directory_base;

		global $network_query, $network_post;

		global $wp_query;

		if ( isset($wp_query->query_vars['namespace']) && $wp_query->query_vars['namespace'] == 'gst' && $wp_query->query_vars['type'] == 'tag' ) {

			//print_r($wp_query);

			$global_site_tags_shown = get_site_option('global_site_tags_shown', '50');
			$global_site_tags_per_page = get_site_option('global_site_tags_per_page', '10');
			$global_site_tags_background_color = get_site_option('global_site_tags_background_color', '#F2F2EA');
			$global_site_tags_alternate_background_color = get_site_option('global_site_tags_alternate_background_color', '#FFFFFF');
			$global_site_tags_border_color = get_site_option('global_site_tags_border_color', '#CFD0CB');
			$global_site_tags_banned_tags = get_site_option('global_site_tags_banned_tags', 'uncategorized');
			$global_site_tags_tag_cloud_order = get_site_option('global_site_tags_tag_cloud_order', 'count');

			$global_site_tags_post_type = get_site_option('global_site_tags_post_type', 'post');

			if( !empty( $wp_query->query_vars['tag'] ) ) {
				// Show the results list for the tag

				//=====================================//
				$parameters = array();

				// Set the page number
				if( !isset($wp_query->query_vars['paged']) || $wp_query->query_vars['paged'] <= 1) {
					$page = 1;
					$start = 0;
				} else {
					$page = $wp_query->query_vars['paged'];
					$math = $wp_query->query_vars['paged'] - 1;
					$math = $global_site_tags_per_page * $math;
					$start = $math;
				}

				if($global_site_tags_post_type != 'all') {
					$parameters['post_type'] = $global_site_tags_post_type;
				} else {
					$post_types = $this->global_site_tags_get_post_types();
					$parameters['post_type'] = $post_types;
				}

				// Add in the start and end numbers
				$parameters['posts_per_page'] = intval( $global_site_tags_per_page );
				$parameters['paged'] = intval( $page );

				// Add in the tags
				$parameters['tag'] = urldecode($wp_query->query_vars['tag']);

				//=====================================//

				if(!empty($wp_query->query_vars['tag'])) {
					$network_query_posts = network_query_posts( $parameters );

					//found_posts
					if( network_have_posts() && isset($GLOBALS['network_query']->found_posts) && $GLOBALS['network_query']->found_posts > intval( $global_site_tags_per_page ) ) {
						$next = 'yes';
						$navigation_content = $this->new_pagination( $GLOBALS['network_query'], $current_site->path . $this->global_site_tags_base . '/' . urlencode($wp_query->query_vars['tag']) );
					}

					if ( network_have_posts() ) {
						$content .= (isset($navigation_content)) ? $navigation_content : '';

						$content .= '<div style="float:left; width:100%">';
						$content .= '<table border="0" width="100%" bgcolor="">';
						$content .= '<tr>';
						$content .= '<td style="background-color:' . $global_site_tags_background_color . '; border-bottom-style:solid; border-bottom-color:' . $global_site_tags_border_color . '; border-bottom-width:1px; font-size:12px;" width="10%"> </td>';
						$content .= '<td style="background-color:' . $global_site_tags_background_color . '; border-bottom-style:solid; border-bottom-color:' . $global_site_tags_border_color . '; border-bottom-width:1px; font-size:12px;" width="90%"><center><strong>' .  __('Posts', 'globalsitesearch') . '</strong></center></td>';
						$content .= '</tr>';

						// Search results

						$avatar_default = get_option('avatar_default');
						$tic_toc = 'toc';

						while( network_have_posts()) {
							network_the_post();

							//=============================//
							$author_id = network_get_the_author_id();
							$the_author = get_user_by( 'id', $author_id );

							if(!$the_author) {
								$post_author_display_name = __('Unknown', 'globalsitetags');
							} else {
								$post_author_display_name = $the_author->display_name;
							}

							$tic_toc = ($tic_toc == 'toc') ? 'tic' : 'toc';
							$bg_color = ($tic_toc == 'tic') ? $global_site_tags_alternate_background_color : $global_site_tags_background_color;

							//=============================//
							$content .= '<tr>';
								$content .= '<td style="background-color:' . $bg_color . '; padding-top:10px; text-align: center;" valign="top" width="10%"><a style="text-decoration:none;" href="' . network_get_permalink() . '">' . get_avatar( $author_id, 32, $avatar_default ) . '</a></td>';
								$content .= '<td style="background-color:' . $bg_color . '; padding-top:10px; vertical-align: top;" width="90%" valign="top">';
								if ( function_exists('members_directory_site_admin_options') ) {
									$post_author_nicename = $the_author->user_nicename;
									$content .= '<strong><a style="text-decoration:none;" href="http://' . $current_site->domain . $current_site->path . $members_directory_base . '/' . $post_author_nicename . '/">' . $post_author_display_name . '</a> ' . __(' wrote', 'globalsitetags') . ': </strong> ';
								} else {
									$content .= '<strong>' . $post_author_display_name . __(' wrote', 'globalsitetags') . ': </strong> ';
								}
								$content .= '<strong><a style="text-decoration:none;" href="' . network_get_permalink() . '">' . network_get_the_title() . '</a></strong><br />';
								$the_content = network_get_the_content();
								$content .= substr(strip_tags( $the_content ),0, 250) . ' (<a href="' . network_get_permalink() . '">' . __('More', 'globalsitetags') . '</a>)';
								$content .= '</td>';
							$content .= '</tr>';

						}


						$content .= '</table>';
						$content .= '</div>';
						$content .= (isset($navigation_content)) ? $navigation_content : '';
					} else {
						$content .= '<p>';
						$content .= '<center>';
						$content .= __('Nothing found for search term(s).', 'globalsitetags');
						$content .= '</center>';
						$content .= '</p>';
					}

				}

			} else {
				// Show the tag cloud
				$content .= $this->global_site_tags_tag_cloud( $content, $global_site_tags_shown, $global_site_tags_tag_cloud_order, 14, 52, '' ,'', $global_site_tags_post_type );
			}

		}

		return $content;

	}

	function new_pagination( $wp_query, $mainlink = '' ) {

		if(empty($wp_query->query_vars['paged'])) {
			$paged = 1;
		} else {
			$paged = $wp_query->query_vars['paged'];
		}

		if((int) $wp_query->max_num_pages > 1) {

			// we can draw the pages
			$html = '';

			$html .= "<div class='gssnav'>";

			$list_navigation = paginate_links( array(
				'base' => trailingslashit($mainlink) . '%_%',
				'format' => 'page/%#%',
				'total' => $wp_query->max_num_pages,
				'current' => $paged,
				'prev_next' => true
			));

			$html .= $list_navigation;

			$html .= "</div>";

			return $html;
		}
	}


	//------------------------------------------------------------------------//
	//---Page Output Functions------------------------------------------------//
	//------------------------------------------------------------------------//

	//------------------------------------------------------------------------//
	//---Support Functions----------------------------------------------------//
	//------------------------------------------------------------------------//

	function global_site_tags_roundup($value, $dp){
	    return ceil($value*pow(10, $dp))/pow(10, $dp);
	}

}

$globalsitetags = new globalsitetags();

?>