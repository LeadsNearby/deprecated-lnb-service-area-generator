<?php
/*
Plugin Name: LNB Bulk Service Area Generator
Plugin URI: http://leadsnearby.com/
Description: Bulk creation of Nearby Now City Pages
Version: 1.3.6
Author: LeadsNearby
Author URI: http://leadsnearby.com
License: GPLv2 or later
*/

// Include Updater Class
if ( is_admin() ) {
	require_once( plugin_dir_path(__FILE__).'/lib/updater/github-updater.php' );
	new GitHubPluginUpdater( __FILE__, 'LeadsNearby', 'lnb-service-area-generator' );
}

if(is_admin()){
    global $table_prefix,$wpdb;
    add_action('admin_menu','auto_generate_plugin_admin_menu');

    function auto_generate_plugin_admin_menu(){
        add_options_page('Manage Contents','LNB Bulk Service Area Pages','administrator','content_settings','LNB_settings_page');
    }  
}

function LNB_settings_page(){
    global $table_prefix,$wpdb;
    if(isset($_REQUEST['action']))
    {
        $cityname_arr = explode("\n",$_REQUEST['cityname']);
		    $statename =$_REQUEST['statename'];
        $title=$_REQUEST['page_title'];
        $link=$_REQUEST['page_link'];
        if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) {	
	        $metatitle=$_REQUEST['metatitle'];
	        $metadescription=$_REQUEST['metadescription'];
	        $focuskeyword=$_REQUEST['focuskeyword'];
        }
        foreach($cityname_arr as $cityname){
            $cityname = str_replace(array("\n","\r","\r\n","\n\r"),"",$cityname);			           
			$title2 = str_ireplace(['[lnb-city]','[lnb-state]'],[$cityname,$statename],$title);
			$link2 = str_ireplace(['[lnb-city]','[lnb-state]'],[$cityname,$statename],$link);
			$page_contents = str_ireplace(['[lnb-city]', '[lnb-state]'], [$cityname, $statename],str_replace(array("\n","\r","\r\n","\n\r")," ",$_REQUEST['page_contents']));
			$meta_title = str_ireplace(['[lnb-city]', '[lnb-state]'], [$cityname, $statename], $metatitle);
			$meta_desc = str_ireplace(['[lnb-city]', '[lnb-state]'], [$cityname, $statename],str_replace(array("\n","\r","\r\n","\n\r")," ",$_REQUEST['metadescription']));
            $focus_keyword = str_ireplace(['[lnb-city]', '[lnb-state]'], [$cityname, $statename],str_replace(array("\n","\r","\r\n","\n\r")," ",$_REQUEST['focuskeyword']));    
            
			$post_array=array(
              'post_content'   => $page_contents,
              'post_status'    => 'publish',
              'post_title'     => $title2,
              'post_type'      => 'page',
              'post_name'      => str_replace(",","-",$link2)
              //'post_name'      => str_replace(",","-",$title2)
            ); // Post Array End			
					
            if(isset($_REQUEST['parent_page']) && $_REQUEST['parent_page']>=1)
            $post_array['post_parent']=$_REQUEST['parent_page'];
            $post = $post_array;
            $id = wp_insert_post($post);
            update_post_meta($id,'lnb-city',$title2);
            if(isset($_REQUEST['page_template']) && $_REQUEST['page_template']!='')
            update_post_meta($id, '_wp_page_template', $_REQUEST['page_template']);
        	if(isset($_REQUEST['schema-itemtype']) && $_REQUEST['schema-itemtype']!='')
        	update_post_meta($id, 'lnb-schema-itemtype', $_REQUEST['schema-itemtype']);

		
		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {	
			if( !is_wp_error($id) && $id > 0 ) {				
				add_post_meta($id, '_yoast_wpseo_title', $meta_title);
				add_post_meta($id, '_yoast_wpseo_metadesc', $meta_desc);
        add_post_meta($id, '_yoast_wpseo_focuskw_text_input', $focus_keyword);
        add_post_meta($id, '_yoast_wpseo_focuskw', $focus_keyword);
			}	
		} elseif ( is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) {	
      if( !is_wp_error($id) && $id > 0 ) {
        add_post_meta($id, '_aioseop_keywords', $focus_keyword);
        add_post_meta($id, '_aioseop_description', $meta_desc);
        add_post_meta($id, '_aioseop_title', $meta_title);
      }
		}
            $update_message = '<div id="message" class="updated"><p>Pages Created. Click here to <a href="edit.php?post_status=publish&post_type=page">View Pages</a></p></div>';

        }//End Foreach cityname_arr
    }//END IF 'Action'
    else{
		$cityname='';
		$statename='';
		$metatitle='';
		$metadescription='';
		$focuskeyword='';  
		$title2='';
		$imagename='';
		$extra_contents='';
		$update_message='';
	}//End Else

    ?>
	<style>
	#bulk-creator-form .error {color: #ff0000; display: block;}
	.dashicons-location-alt::before {content: "\f231";}	
	</style>
	<div id="lnb-service-area-form">
		<?php echo $update_message; ?>
		<h2><?php echo _e('Auto Generated Page Contents Options');?></h2>
		<form id="bulk-creator-form" method="post" action="">
			<?php wp_nonce_field('update-options'); ?>
			<table>
				<tr>
					<td valign="top"><?php echo _e('City Text Field');?></td>
					<td>
						<textarea id="cityname" type="text" cols="40" rows="5" name="cityname" placeholder="Ex: Raleigh"></textarea>
						<br><label><?php _e("Please list one City,State per line"); ?></label>
					</td>
				</tr>
				<tr>
					<td valign="top"><?php echo _e('State Field');?></td>
					<td>
						<input id="statename" type="text" cols="40" rows="5" name="statename" placeholder="Ex:NC" />
						<br><label><?php _e("Please enter the state"); ?></label>
					</td>
				</tr>  
				<tr>
					<td valign="top"><?php echo _e('Parent Page');?></td>
					<td>
					<?php
					$pages = get_pages();
					if($pages && !empty($pages)){
						echo "<select name='parent_page' id='parent_page' ><option value='0'>None</option>";
						foreach ( $pages as $page ) {
							$option = '<option value="' . $page->ID . '">';
							$option .= $page->post_title;
							$option .= '</option>';
							echo $option;
						}
						echo "</select>";
					}// End if Pages
					?>
					</td>
				</tr>
				<tr>
					<td valign="top"><?php echo _e('Page Template');?></td><td>		
					<?php
					$templates = get_page_templates();
					if($templates && !empty($templates)){
						echo "<select name='page_template' id='page_template' ><option value=''>Default</option>";
						foreach ( $templates as $template_name => $template_filename ) {
						$option = '<option value="' . $template_filename  . '">';
						$option .= $template_name;
						$option .= '</option>';
						echo $option;
						}
						echo "</select>";
					} //End IF Templates
					?>
					</td>
				</tr>
				<?php 
				$theme = wp_get_theme();
				$theme_name = $theme->name;
				$theme_parent = $theme->parent();

				if ($theme_name = 'Avada' || $theme_parent = 'Avada' ) :  ?>
				<tr>
					<td>Sidebar</td>
					<td>
						<select name='fusion_page_sidebar' id='fusion_page_sidebar' ><option value='no-sidebar'>No sidebar</option>
						<?php
							global $wp_registered_sidebars;
							foreach ($wp_registered_sidebars as $sidebar) {
								$option = '<option value="'.$sidebar['name'].'">'.$sidebar['name'].'</option>';
								echo $option;
							}
						?>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<td valign="top"><?php echo _e('Page Title');?></td>
					<td>
						<input placeholder="<?php echo _e('ex: Find Web Design in [lnb-city]');?>" type="text" name="page_title" id="page_title" size="70">
					</td>
				</tr>
				<tr>
					<td valign="top"><?php echo _e('Permalink');?></td>
					<td>
						<input placeholder="<?php echo _e('ex: find-web-design-[lnb-city]-[lnb-state]');?>" type="text" name="page_link" id="page_link" size="70">
					</td>
				</tr>
				<tr>
					<td valign="top" ><?php echo _e('Page Contents');?></td>
					<td>
						<!--<textarea class="tinymce-enabled" rows="15" cols="120"name="page_contents" id="page_contents"></textarea>-->
						<?php
							wp_editor($page_contents, 'page_contents', array(
								'wpautop' => false,
								'media_buttons' => true,
								'textarea_name' => 'page_contents',
								'textarea_rows' => 10,
								'teeny' => true,
								'tinymce' => true,
								'drag_drop_upload' => true
							));
						?>						
						<br />
						<?php echo _e('HTML Markup accepted as well as the [lnb-city] shortcode. Shortcode will be transformed into your city name.'); ?>
					</td>
				</tr>
				<?php if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) { ?>
				<tr>
					<td valign="top"><?php echo _e('Meta Title');?></td>
					<td>
						<input id="metatitle" type="text" cols="80" rows="5" name="metatitle" size="70" placeholder="Enter Meta Title Here" />
						<br><label><?php _e("Please enter the Meta Title Here"); ?></label>
					</td>
				</tr>
				<tr>
					<td valign="top"><?php echo _e('Meta Description');?></td>
					<td>
						<textarea id="metadescription" type="text" rows="5" cols="120" name="metadescription" maxlength="190"></textarea>
						<br /><label>The specified meta description should be have a maximum of 156 characters toensure the entire description is visible. You currently have <span id="chars">156</span> characters remaining</label>
					</td>
				</tr>
        		<tr>
					<td valign="top"><?php echo _e('Focus Keyword');?></td>
					<td>
						<input id="focuskeyword" type="text" cols="80" rows="5" name="focuskeyword" size="70" placeholder="Enter the main keyword or keyphrase" />
						<br><label><?php _e("Pick the main keyword or keyphrase that this post/page is about."); ?></label>
					</td>
				</tr>
				<?php } ?>
				<?php if( is_plugin_active( 'schema-options/main.php' ) ) { ?>
				<tr>
					<td valign="top"><?php echo _e('Schema ItemType');?></td>
					<td>
						<select id="schema-itemtype" type="text" name="schema-itemtype">
						<?php
						$lnb_schema_itemtype_array = schema_admin_page::get_schema_itemtypes();
						foreach ($lnb_schema_itemtype_array as $option) { ?>
							<option value="<?php echo $option['value']?>"><?php echo $option['title']?></option>
						<?php } ?>
						</select>
					</td>
				</tr>
				<?php } ?>
			</table>
			<input type="hidden" name="action" value="update" >
			<p><input type="submit" value="<?php echo _e('Create Service Area Pages'); ?>"></p>
		</form>
	</div>
<?php
}// End Function LNB_settings_page

add_action('admin_enqueue_scripts', 'lnb_bulk_plugin_scripts');
function lnb_bulk_plugin_scripts()  {
	if (is_admin()) {
		wp_register_script( 'jquery-validate', 'http://ajax.aspnetcdn.com/ajax/jquery.validate/1.14.0/jquery.validate.min.js', TRUE);
		wp_enqueue_script('jquery-validate');
	}		
}

add_filter('admin_head','ShowTinyMCE');
function ShowTinyMCE() {
	// conditions here
	wp_enqueue_script( 'common' );
	wp_enqueue_script( 'jquery-color' );
	wp_print_scripts('editor');
	if (function_exists('add_thickbox')) add_thickbox();
	wp_print_scripts('media-upload');
	if (function_exists('wp_tiny_mce')) wp_tiny_mce();
	wp_admin_css();
	wp_enqueue_script('utils');
	do_action("admin_print_styles-post-php");
	do_action('admin_print_styles');
}

    //Action target that adds the "Insert NearbyNow" button to the post/page edit screen
	add_action('media_buttons', 'add_bulk_nearbynow_button', 20);
    function add_bulk_nearbynow_button(){

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

    //Action target that displays the popup to insert a form to a post/page
	add_action('admin_footer', 'add_nearbynow_popup');
    function add_nearbynow_popup(){
        ?>
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
    }

add_action('admin_footer','my_tinyMCE');
function my_tinyMCE(){
        echo "
        <script>
		jQuery(document).ready(function(){
			//tinyMCE.init({
				//mode : 'specific_textareas',
				//theme : 'modern', 
				/*plugins : 'autolink, lists, spellchecker, style, layer, table, advhr, advimage, advlink, emotions, iespell, inlinepopups, insertdatetime, preview, media, searchreplace, print, contextmenu, paste, directionality, fullscreen, noneditable, visualchars, nonbreaking, xhtmlxtras, template',*/
				//editor_selector :'tinymce-enabled'
			//});
			//Counts the amount characters left in the Meta Description Textarea
			var maxLength = 156;
			jQuery('#metadescription').keyup(function() {
				var length = jQuery(this).val().length;
				var length = maxLength-length;
				jQuery('#chars').text(length);
			});
			
			//Form Validation
			jQuery('#bulk-creator-form').validate({
				rules: {
					cityname: 'required',
					statename: 'required',
					page_title: 'required',
					page_contents: 'required'
					
				},
				messages: {
					cityname: 'Please enter at least one city',
					statename: 'Please enter a state',
					page_title: 'Please enter a page title',
					page_contents: 'Please enter some content for the page'
				}
			});			
		});
        </script>
        ";
}
?>