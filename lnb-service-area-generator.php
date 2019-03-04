<?php
/*
Plugin Name: LeadsNearby Bulk Service Area Generator
Plugin URI: http://leadsnearby.com/
Description: Bulk creation of Nearby Now City Pages
Version: 2.0.4
Author: LeadsNearby
Author URI: http://leadsnearby.com
License: GPLv2 or later
*/

class LeadsNearbySAG {

	private static $instance = null;
	public $update_message = '';

	public static function getInstance() {

		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	function update() {
		require_once( plugin_dir_path( __FILE__ ).'/lib/updater/github-updater.php' );
		new GitHubPluginUpdater( __FILE__, 'LeadsNearby', 'lnb-service-area-generator' );
	}

	function add_admin_page() {
		add_options_page(
			'Bulk Create Nearby Now Service Area Pages',
			'Service Area Page Generator',
			'administrator',
			'create_service_area_pages',
			[ $this, 'render_settings_page' ]
		);
	}

	function add_pages() {

		if( ! wp_verify_nonce( $_POST['_wpnonce'], 'lnb-sag' ) ) {
			die();
		}

		$raw_cities = explode( PHP_EOL, $_POST['new_page']['cities']) ;
		$cities = $this->fix_city_array( $raw_cities );

		$state = $_POST['new_page']['state'];

		$counter = 0;
		foreach( $cities as $city ) {

			// Run through post_fields array, replace and use for post_array
			$post_array = $this->get_post_fields( $city, $state );
			// Run through post_meta array, replace and use for meta_input array
			$post_meta_array = $this->get_post_meta( $city, $state );

			$post_array['meta_input'] = $post_meta_array;		
			  
			$id = wp_insert_post( $post_array, true );

			if( !is_wp_error( $id ) ) {
				$counter++;
			}

		}
		
		$this->update_message = '<div id="message" class="updated"><p>'.$counter.' page(s) Created. Click here to <a href="edit.php?post_status=publish&post_type=page">View Pages</a></p></div>';
	}

	function get_post_fields( $city, $state ) {

		$fields = $_POST['post_fields'];
		$sanitized_fields = array(
			'post_status'    => 'publish',
			'post_type'      => 'page',
		);

		foreach( $fields as $field => $field_value ) {

			if( $field != 'post_content' ) {
				$field_value = $this->remove_line_breaks( $field_value );
			}

			$sanitized_fields[$field] = $this->replace_shortcodes( $city, $state, $field_value );

		}

		return array_filter( $sanitized_fields );

	}

	function get_post_meta( $city, $state ) {

		$metas = $_POST['post_meta'];

		foreach( $metas as $meta => $meta_value ) {

			if( $meta == 'sbg_selected_sidebar_replacement' ) {
				$sanitized_metas[$meta][0] = $this->replace_shortcodes( $city, $state, $this->remove_line_breaks( $meta_value ) );	
			} else {
				$sanitized_metas[$meta] = $this->replace_shortcodes( $city, $state, $this->remove_line_breaks( $meta_value ) );
			}

		}

		return array_filter( $sanitized_metas );

	}

	function fix_city_array( $array ) {
		$cities = array();

		foreach( $array as $city ){
			$cities[] = trim( $city );
		}

		return $cities;
	}

	function remove_line_breaks( $string ) {

		$new_string = str_replace( [ '\n', '\r', '\r\n', '\n\r' ], '', $string );

		return $new_string;

	}

	function replace_shortcodes( $city, $state, $string ) {

		$new_string = str_ireplace( [ '[lnb-city]', '[lnb-state]' ], [ $city, $state ], $string );

		return $new_string;

	}

	function render_settings_page() {
		
		if( isset( $_POST['action'] ) && $_POST['action'] == 'update' ) {
			$this->add_pages();
		}
	
		$page_parents = get_pages();
		$page_templates = get_page_templates();
		global $wp_registered_sidebars;
		$theme = wp_get_theme();
		$theme_name = $theme->name;
		$theme_parent = $theme->parent();
	
		ob_start(); ?>
		<style>
			#bulk-creator-form {
				width:100%;
				max-width:760px;
			}
			#bulk-creator-form tr:not(:after) {
				margin-bottom:8px;
				border-collapse:separate;
				border-spacing:5em;
			}
			#bulk-creator-form td {
				padding:8px;
			}
			#bulk-creator-form .field {
				width:100%;
				min-height:40px;
			}
			#bulk-creator-form textarea.field  {
				min-height:150px;
			}
			#bulk-creator-form select.field {
				max-width:225px;
			}
			#bulk-creator-form label {
				display:block;
				margin-bottom:5px;
				margin-left:1px;
				font-size:0.85rem;
				font-weight:bold
			}
			#bulk-creator-form .error {
				color:#ff0000;
				display:block;
			}
			.dashicons-location-alt::before {
				content:"\f231";
			}
		</style>
		<div id="lnb-service-area-form">
			<h2><?php echo _e('Auto Generated Page Contents Options');?></h2>
			<?php echo $this->update_message; ?>
			<p>[lnb-city] and [lnb-state] shortcodes are accepted in all fields. Shortcodes will be transformed into your city name and state respectively.</p>
			<form id="bulk-creator-form" method="post" action="">
				<?php echo wp_nonce_field( 'lnb-sag' ); ?>
				<table style="width:100%">
					<tbody>
						<tr>
							<td colspan="3">
								<label>Cities</label>
								<textarea required class="field" name="new_page[cities]" placeholder="List cities here, one per line"></textarea>
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<label>State</label>
								<select required class="field" name="new_page[state]">
									<option selected disabled>-- Select State --</option>
									<option value="AL">Alabama (AL)</option>
									<option value="AK">Alaska (AK)</option>
									<option value="AZ">Arizona (AZ)</option>
									<option value="AR">Arkansas (AR)</option>
									<option value="CA">California (CA)</option>
									<option value="CO">Colorado (CO)</option>
									<option value="CT">Connecticut (CT)</option>
									<option value="DE">Delaware (DE)</option>
									<option value="DC">District Of Columbia (DC)</option>
									<option value="FL">Florida (FL)</option>
									<option value="GA">Georgia (GA)</option>
									<option value="HI">Hawaii (HI)</option>
									<option value="ID">Idaho (ID)</option>
									<option value="IL">Illinois (IL)</option>
									<option value="IN">Indiana (IN)</option>
									<option value="IA">Iowa (IA)</option>
									<option value="KS">Kansas (KS)</option>
									<option value="KY">Kentucky (KY)</option>
									<option value="LA">Louisiana (LA)</option>
									<option value="ME">Maine (ME)</option>
									<option value="MD">Maryland (MD)</option>
									<option value="MA">Massachusetts (MA)</option>
									<option value="MI">Michigan (MI)</option>
									<option value="MN">Minnesota (MN)</option>
									<option value="MS">Mississippi (MS)</option>
									<option value="MO">Missouri (MO)</option>
									<option value="MT">Montana (MT)</option>
									<option value="NE">Nebraska (NE)</option>
									<option value="NV">Nevada (NV)</option>
									<option value="NH">New Hampshire (NH)</option>
									<option value="NJ">New Jersey (NJ)</option>
									<option value="NM">New Mexico (NM)</option>
									<option value="NY">New York (NY)</option>
									<option value="NC">North Carolina (NC)</option>
									<option value="ND">North Dakota (ND)</option>
									<option value="OH">Ohio (OH)</option>
									<option value="OK">Oklahoma (OK)</option>
									<option value="OR">Oregon (OR)</option>
									<option value="PA">Pennsylvania (PA)</option>
									<option value="RI">Rhode Island (RI)</option>
									<option value="SC">South Carolina (SC)</option>
									<option value="SD">South Dakota (SD)</option>
									<option value="TN">Tennessee (TN)</option>
									<option value="TX">Texas (TX)</option>
									<option value="UT">Utah (UT)</option>
									<option value="VT">Vermont</option>
									<option value="VA">Virginia</option>
									<option value="WA">Washington</option>
									<option value="WV">West Virginia</option>
									<option value="WI">Wisconsin</option>
									<option value="WY">Wyoming</option>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<label>Page Title</label>
								<input required type="text" class="field" name="post_fields[post_title]" placeholder="Enter page title template">
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<label>Page URL</label>
								<input type="text" class="field" name="post_fields[post_name]" placeholder="Enter page url template (if different than title)">
							</td>
						</tr>
						<?php if( $page_parents || $page_templates || $theme_name = 'Avada' || $theme_parent = 'Avada' ) : ?>
						<tr>
						<?php if( $page_parents ) : ?>
							<td width="30%">
								<label>Page Parent</label>
								<select class="field" name="post_fields[post_parent]">
									<option disabled selected>-- Select Parent --</option>
									<option>No Parent</option>
									<?php foreach( $page_parents as $page ) : ?>
									<option value="<?php echo $page->ID; ?>"><?php echo $page->post_title; ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<?php endif; ?>
							<?php if( $page_templates ) : ?>
							<td width="30%">
								<label>Page Template</label>
								<select class="field" name="post_meta[_wp_page_template]">
									<option disabled selected>-- Select Page Template --</option>
									<option>Default Template</option>
									<?php foreach( $page_templates as $template_name => $template_filename ) : ?>
									<option value="<?php echo $template_filename; ?>"><?php echo $template_name; ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<?php endif; ?>
							<?php if( $theme_name = 'Avada' || $theme_parent = 'Avada' ) : ?>
							<td width="30%">
								<label>Sidebar</label>
								<select class="field" name="post_meta[sbg_selected_sidebar_replacement]">
									<option disabled selected>-- Select Sidebar --</option>
									<option>No Sidebar</option>
									<?php foreach ($wp_registered_sidebars as $sidebar) { ?>
									<option value="<?php echo $sidebar['name']; ?>"><?php echo $sidebar['name']; ?></option>
									<?php } ?>
								</select>
							</td>
							<?php endif; ?>
						</tr>
						<?php endif; ?>
						<tr>
							<td colspan="3">
								<label>Page Content</label>
								<!--<textarea class="tinymce-enabled" rows="15" cols="120" name="post_fields[post_content]" id="post_content"></textarea>-->
								<?php
									wp_editor($page_contents, 'post_content', array(
										'wpautop' => false,
										'media_buttons' => true,
										'textarea_name' => 'post_fields[post_content]',
										'textarea_rows' => 10,
										'teeny' => true,
										'tinymce' => true,
										'drag_drop_upload' => true
									));
								?>
							</td>
						</tr>
						<?php if( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) : ?>
						<tr>
							<td colspan="3">
								<input type="text" class="field" name="post_meta[_yoast_wpseo_title]" placeholder="Enter Yoast page title">
							</td>
						</tr>
						
						<tr>
							<td colspan="3">
								<textarea class="field" name="post_meta[_yoast_wpseo_metadesc]" placeholder="Enter Yoast meta description"></textarea>
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<input type="text" class="field" name="post_meta[_yoast_wpseo_focuskw]" placeholder="Enter Yoast focus keyword">
							</td>
						</tr>
						<?php endif; ?>
						<?php if( is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) && !is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) : ?>
						<tr>
							<td colspan="3">
								<input type="text" class="field" name="post_meta[_aioseop_title]" placeholder="Enter All in One SEO page title">
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<textarea class="field" name="post_meta[_aioseop_description]" placeholder="Enter All in One SEO neta description"></textarea>
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<input type="text" class="field" name="post_meta[_aioseop_keywords]" placeholder="Enter All in One SEO focus keywords">
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
				<input type="hidden" name="action" value="update">
				<p><input type="submit" value="<?php echo _e('Create Service Area Pages'); ?>"></p>
			</form>
		</div>
		<script>
			function InsertNearbyNow(){
				var bulk_nearbynow_state = jQuery("#bulk_nearbynow_state").is(":checked");
				var bulk_nearbynow_city = jQuery("#display_nearbynow_city").is(":checked");
				var bulk_nearbynow_showmap = jQuery("#bulk_nearbynow_showmap").is(":checked");
				var bulk_nearbynow_reviewstart= jQuery("#bulk_nearbynow_reviewstart").is(":checked");
				var bulk_nearbynow_reviewcount = jQuery("#bulk_nearbynow_reviewcount").is(":checked");
				var bulk_nearbynow_checkinstart = jQuery("#bulk_nearbynow_checkinstart").is(":checked");
				var bulk_nearbynow_checkincount = jQuery("#bulk_nearbynow_checkincount").is(":checked");
				var bulk_nearbynow_zoomlevel = jQuery("#bulk_nearbynow_zoomlevel").is(":checked");
				var bulk_nearbynow_reviewcityurl = jQuery("#bulk_nearbynow_reviewcityurl").is(":checked");
				var bulk_nearbynow_mapsize = jQuery("#bulk_nearbynow_mapsize").is(":checked");
				var bulk_nearbynow_mapscrollwheel = jQuery("#bulk_nearbynow_mapscrollwheel").is(":checked");
				var bulk_nearbynow_fblike = jQuery("#bulk_nearbynow_fblike").is(":checked");
				var bulk_nearbynow_fbcomment = jQuery("#bulk_nearbynow_fbcomment").is(":checked");
				var bulk_nearbynow_serviceareaname = jQuery("#bulk_nearbynow_serviceareaname").is(":checked");
				var state_nn = bulk_nearbynow_state ? "[lnb-state]" : "";
				var city_nn = bulk_nearbynow_city ? "[lnb-city]" : "";
				var showmap_nn = bulk_nearbynow_showmap ? "yes" : "";
				var reviewstart_nn = bulk_nearbynow_reviewstart ? "1" : "";
				var reviewcount_nn = bulk_nearbynow_reviewcount ? "25" : "";
				var checkinstart_nn = bulk_nearbynow_checkinstart ? "1" : "";
				var checkincount_nn = bulk_nearbynow_checkincount ? "25" : "";
				var zoomlevel_nn = bulk_nearbynow_zoomlevel ? "9" : "";
				var reviewcityurl_nn = bulk_nearbynow_reviewcityurl ? "<?php echo site_url(); ?>" : "";
				var mapsize_nn = bulk_nearbynow_mapsize ? "large" : "";
				var mapscrollwheel_nn = bulk_nearbynow_mapscrollwheel ? "no" : "";
				var fblike_nn = bulk_nearbynow_fblike ? "yes" : "";
				var fbcomment_nn = bulk_nearbynow_fbcomment ? "no" : "";
				var serviceareaname_nn = bulk_nearbynow_serviceareaname ? "no" : "";
				window.send_to_editor("[serviceareareviewcombo city=\"" + city_nn +"\" state=\"" + state_nn +"\" showmap=\"" + showmap_nn +"\" reviewstart=\"" + reviewstart_nn +"\" reviewcount=\"" + reviewcount_nn +"\" checkinstart=\"" + checkinstart_nn +"\" checkincount=\"" + checkincount_nn +"\" zoomlevel=\"" + zoomlevel_nn +"\" reviewcityurl=\"" + reviewcityurl_nn +"\" mapsize=\"" + mapsize_nn +"\" mapscrollwheel=\"" + mapscrollwheel_nn +"\" fblike_nn=\"" + bulk_nearbynow_fblike + "\" fbcomment=\"" + fbcomment_nn +"\"]");
			}
		</script>

		<div id="select_nearbynow" style="display:none;">
			<div class="wrap">
				<div>
					<div style="padding:15px 15px 0 15px;">
						<h3 style="color:#5A5A5A!important; font-family:Georgia,Times New Roman,Times,serif!important; font-size:1.8em!important; font-weight:normal!important;"><?php _e("Insert NearbyNow shortcode", "bulk_nearbynow"); ?></h3>
					</div>
					<div style="padding:15px 15px 0 15px;">
						<!-- New Fields -->
						<input type="checkbox" id="bulk_nearbynow_state" /> <label for="bulk_nearbynow_state"><?php _e("State Shortcode", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="display_nearbynow_city" /> <label for="display_nearbynow_city"><?php _e("City Shortcode", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_showmap" /> <label for="bulk_nearbynow_showmap"><?php _e("Show Map", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_reviewstart" /> <label for="bulk_nearbynow_reviewstart"><?php _e("Review Start", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_reviewcount" /> <label for="bulk_nearbynow_reviewcount"><?php _e("Review Count", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_checkinstart" /> <label for="bulk_nearbynow_checkinstart"><?php _e("Checkin Start", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_checkincount" /> <label for="bulk_nearbynow_checkincount"><?php _e("Checkin Count", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_zoomlevel" /> <label for="bulk_nearbynow_zoomlevel"><?php _e("Zoom Level", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_reviewcityurl" /> <label for="bulk_nearbynow_reviewcityurl"><?php _e("Review URL", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_mapsize" /> <label for="bulk_nearbynow_mapsize"><?php _e("Map Size", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_mapscrollwheel" /> <label for="bulk_nearbynow_mapscrollwheel"><?php _e("Map Scroll Wheel", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_fblike" /> <label for="bulk_nearbynow_fblike"><?php _e("Facebook Like", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_fbcomment" /> <label for="bulk_nearbynow_fbcomment"><?php _e("Facebook Comment", "bulk_nearbynow"); ?></label><br />
						<input type="checkbox" id="bulk_nearbynow_serviceareaname" /> <label for="bulk_nearbynow_serviceareaname"><?php _e("Service Area Name", "bulk_nearbynow"); ?></label><br />
					</div>
					<div style="padding:15px;">
						<input type="button" class="button-primary" value="<?php _e("Insert NearbyNow Shortcode", "bulk_nearbynow"); ?>" onclick="InsertNearbyNow();"/>&nbsp;&nbsp;&nbsp;
					<a class="button" style="color:#bbb;" href="#" onclick="tb_remove(); return false;"><?php _e("Cancel", "bulk_nearbynow"); ?></a>
					</div>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		echo $html;
	}// End Function LNB_settings_page

	function add_nearbynow_button() {

		// do a version check for the new 3.5 UI
		$version = get_bloginfo('version');
	
		if ($version < 3.5) {
			// show button for v 3.4 and below
			$image_btn = plugins_url('/images/form-button.png', __FILE__);
			echo '<a href="#TB_inline?width=480&inlineId=select_nearbynow" class="thickbox" id="add_bulk_nearbynow" title="' . __("Add NearbyNow Shortcode", 'bulk_nearbynow') . '"><img src="'.$image_btn.'" alt="' . __("Add NearbyNow Shortcode", 'bulk_nearbynow') . '" /></a>';
		} else {
			// display button matching new UI
			echo '<a href="#TB_inline?width=480&inlineId=select_nearbynow" class="thickbox button gform_media_link" id="add_bulk_nearbynow" title="' . __("Add NearbyNow Shortcode", 'bulk_nearbynow') . '"><span style="padding-top: 3px;" class="dashicons dashicons-location-alt"></span> ' . __("Add NearbyNow", "bulk_nearbynow") . '</a>';
		}

	}

}

if( ! is_admin() ) {
	return;
}

$leadsnearby_sag = LeadsNearbySAG::getInstance();

add_action( 'plugins_loaded', [ $leadsnearby_sag, 'update' ] );
add_action( 'admin_menu', [ $leadsnearby_sag, 'add_admin_page' ] );

//Action target that adds the "Insert NearbyNow" button to the post/page edit screen
add_action('media_buttons', [ $leadsnearby_sag, 'add_nearbynow_button' ], 20);

?>
