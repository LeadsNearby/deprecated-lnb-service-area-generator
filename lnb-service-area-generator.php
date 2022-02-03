<?php
/*
Plugin Name: Bulk Service Area Page Generator
Plugin URI: https://www.localgladiator.com
Description: Bulk creation of Nearby Now Service Area/City Pages
Version: 2.2
Author: Local Gladiator
Author URI: https://www.localgladiator.com
Tested up to: 5.8.3
Requires at least: 5.0.0
Requires PHP: 7.0
 */

namespace localgl\plugins\sag;

if (!defined('ABSPATH')) {
  exit;
}
// Exit if accessed directly

define('SAG_PLUGIN_SLUG', plugin_basename(__DIR__));
define('SAG_PLUGIN_FILE_NAME', plugin_basename(__FILE__));

require_once plugin_dir_path(__FILE__) . 'class-updater.php';
require_once plugin_dir_path(__FILE__) . 'class-val.php';

add_action('admin_init', function () {
  if (!class_exists('\lnb\core\utils\GitHubPluginUpdater')) {
    require_once plugin_dir_path(__FILE__) . 'class-updater.php';
    new \localgl\plugins\serviceareagenerator\Updater();
  }
}, 99);

class ServiceAreaGenerator {

  private static $instance = null;
  private $update_message = '';
  private $pages_created = -1;
  private $pages_failed = array();
  private $transient;
  private $option;

  private function __construct() {
    add_action('admin_init', [$this, 'add_pages']);
    add_action('admin_notices', [$this, 'show_admin_notices']);

    // Temporary Function for quicker mass replacing
    register_activation_hook(SAG_PLUGIN_FILE_NAME, [$this, 'temp_add_license']);

    $this->transient = get_transient('localglSAG');
    $this->option = get_option('localglSAG');
  }

  // Temporary Function for quicker mass replacement
  public function temp_add_license() {
    $license = get_option('localglSAG');
    $widgets_license = get_option('lnbNNWidgets');

    if (!empty($widgets_license) && empty($license)) {
      update_option('localglSAG', $widgets_license);
    }
  }

  public static function getInstance() {

    if (self::$instance == null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  public function add_admin_page() {
    add_options_page(
      'Bulk Create Nearby Now Service Area Pages',
      'Service Area Page Generator',
      'publish_posts',
      'create_service_area_pages',
      [$this, 'render_settings_page']
    );
  }

  public function show_admin_notices() {

    ob_start();

    if ($this->pages_created > 0): ?>

<div class="notice notice-success is-dismissible">
  <p><?php echo $this->pages_created; ?> page(s) Created. Click here to <a
      href="edit.php?post_status=publish&post_type=page">View Pages</a></p>
</div>

<?php endif;

    if ($this->pages_failed): ?>
<div class="notice notice-error is-dismissible">
  <p><?php echo count($this->pages_failed); ?> page(s) could not be created</p>
  <ul>
    <?php foreach ($this->pages_failed as $city_state => $failed_page): ?>
    <?php
$raw_post_name = isset($failed_page['post_name']) ? $failed_page['post_name'] : $failed_page['post_title'];
    ?>
    <li><?php echo $city_state; ?> (<?php echo sanitize_title_for_query($raw_post_name); ?>)</li>
    <?php endforeach;?>
  </ul>
</div>
<?php endif;

    echo ob_get_clean();
  }

  public function add_pages() {

    if (isset($_POST['action']) && $_POST['action'] == 'add_sa_pages') {

      if (!wp_verify_nonce($_POST['_wpnonce'], 'lnb-sag')) {
        die();
      }

      $raw_cities = explode(PHP_EOL, $_POST['new_page']['cities']);
      $cities = $this->fix_city_array($raw_cities);

      $state = $_POST['new_page']['state'];

      $counter = 0;
      foreach ($cities as $city) {

        // Run through post_fields array, replace and use for post_array
        $post_array = $this->get_post_fields($city, $state);

        // Make sure a page with the same slug doesn't already exist
        $raw_post_name = isset($post_array['post_name']) ? $post_array['post_name'] : $post_array['post_title'];
        $query_args = array(
          'pagename' => sanitize_title_for_query($raw_post_name),
        );
        if (isset($post_array['post_parent'])) {
          $query_args['pagename'] = get_post($post_array['post_parent'])->post_name . '/' . $query_args['pagename'];
        }
        $checker_query = new WP_Query($query_args);
        if ($checker_query->have_posts()) {
          $this->pages_failed["{$city}, {$state}"] = $post_array;
          continue;
        }

        // Run through post_meta array, replace and use for meta_input array
        $post_meta_array = $this->get_post_meta($city, $state);

        $post_array['meta_input'] = $post_meta_array;

        $id = wp_insert_post($post_array, true);

        if (!is_wp_error($id)) {
          $counter++;
        }

      }

      $this->pages_created = $counter;

    }
  }

  public function get_post_fields($city, $state) {

    $fields = $_POST['post_fields'];
    $sanitized_fields = array(
      'post_status' => 'publish',
      'post_type'   => 'page',
    );

    foreach ($fields as $field => $field_value) {

      if ($field != 'post_content') {
        $field_value = $this->remove_line_breaks($field_value);
      }

      $sanitized_fields[$field] = $this->replace_shortcodes($city, $state, $field_value);

    }

    return array_filter($sanitized_fields);

  }

  public function get_post_meta($city, $state) {

    $metas = $_POST['post_meta'];

    foreach ($metas as $meta => $meta_value) {

      if ($meta == 'sbg_selected_sidebar_replacement') {
        $sanitized_metas[$meta][0] = $this->replace_shortcodes($city, $state, $this->remove_line_breaks($meta_value));
      } else {
        $sanitized_metas[$meta] = $this->replace_shortcodes($city, $state, $this->remove_line_breaks($meta_value));
      }

    }

    return array_filter($sanitized_metas);

  }

  public function fix_city_array($array) {
    $cities = array();

    foreach ($array as $city) {
      $cities[] = trim($city);
    }

    return $cities;
  }

  public function remove_line_breaks($string) {

    $new_string = str_replace(['\n', '\r', '\r\n', '\n\r'], '', $string);

    return $new_string;

  }

  public function replace_shortcodes($city, $state, $string) {

    $new_string = str_ireplace(['[lnb-city]', '[lnb-state]'], [$city, $state], $string);

    return $new_string;

  }

  public function render_settings_page() {

    $page_parents = get_pages();
    $page_templates = get_page_templates();
    global $wp_registered_sidebars;
    $theme = wp_get_theme();
    $theme_name = $theme->name;
    $theme_parent = $theme->parent();

    // Licensing Addition
    if (isset($_POST['JbxbMsiGnF_lnb_validate']) && wp_verify_nonce($_POST['JbxbMsiGnF_lnb_validate'], '1R6OqYQJLr_lnb_validate')) {
      $save_key = str_replace(' ', '', $_POST['lnb_license_validation']);
      update_option('localglSAG', $save_key);
      $activation_resp = \localgl\plugins\serviceareagenerator\ConfirmVal::activate($save_key);
    }

    $urlparts = parse_url(home_url());
    $dom_check = $urlparts['host'];
    $lic_key = get_option('localglSAG');
    $lic_active = false;
    $lic_problem = false;

    if (!empty($lic_key)) {
      $validation_resp = \localgl\plugins\serviceareagenerator\ConfirmVal::val($lic_key);

      if (!is_wp_error($validation_resp)) {
        $validation_body = json_decode($validation_resp['body']);
        $check = false;

        if (!empty($validation_body->registered_domains)) {
          foreach ($validation_body->registered_domains as $domain) {
            if ($domain->registered_domain === $dom_check) {
              $check = true;
            }
          }
        }

        set_transient('localglSAG', $validation_body, 3600); // When checking, make sure the license key in the transient matches the DB
        if (@$validation_body->status === 'active' && $check === true) {
          $lic_active = true;
        } else {
          $lic_problem = true;
          //delete_transient('localglSAG');
        }
      }
    }

    $msg = base64_decode('PHAgc3R5bGU9Im1hcmdpbi1ib3R0b206IDVweDsiPlZhbGlkIExpY2Vuc2UgTm90IEZvdW5kIGZvciBMb2NhbCBHbGFkaWF0b3IgQnVsayBTZXJ2aWNlIEFyZWEgUGFnZSBHZW5lcmF0b3IgPC9wPgoJICAgICAgICAgICAgICA8cD5QbGVhc2UgY29udGFjdAoJICAgICAgICAgICAgICA8YSBocmVmPSJodHRwczovL3d3dy5sb2NhbGdsYWRpYXRvci5jb20iPkxvY2FsIEdsYWRpYXRvcjwvYT4KCSAgICAgICAgICAgICAgdG8gcmVxdWVzdCBhIHZhbGlkIGxpY2Vuc2UgPC9wPg==');

    if (class_exists(base64_decode('bG5iXG5ud2lkZ2V0c1xDb25maXJtVmFs'))) {
      if (is_object($this->transient) && @$this->transient->status === 'active') {
        $body = $this->transient;
        $status = $this->transient;
      } elseif (!empty($this->option)) {
        $lk = $this->option;
        $v = call_user_func(base64_decode('bG5iXG5ud2lkZ2V0c1xDb25maXJtVmFsOjp2YWw='), $lk);
        $body = json_decode($v['body']);
        if (@$body->status === 'active') {
          set_transient('lnbNNWidgets', $body, 3600);
        }
        $status = json_decode($v['body']);
      } else {
        return $msg;
      }
    }

    ob_start();?>
<style>
#bulk-creator-form {
  width: 100%;
  max-width: 760px;
}

#bulk-creator-form tr:not(:after) {
  margin-bottom: 8px;
  border-collapse: separate;
  border-spacing: 5em;
}

#bulk-creator-form td {
  padding: 8px;
}

#bulk-creator-form .field {
  width: 100%;
  min-height: 40px;
}

#bulk-creator-form textarea.field {
  min-height: 150px;
}

#bulk-creator-form select.field {
  max-width: 225px;
}

#bulk-creator-form label {
  display: block;
  margin-bottom: 5px;
  margin-left: 1px;
  font-size: 0.85rem;
  font-weight: bold
}

#bulk-creator-form .error {
  color: #ff0000;
  display: block;
}

.dashicons-location-alt::before {
  content: "\f231";
}

/* Licensing Additions */
.lnb-license-text-input {
  height: 40px !important;
  box-shadow: 1px 1px 1px 2px rgb(215 215 215 / 65%) !important;
  border: 1px solid #D6D6D6 !important;
  border-radius: unset !important;
  margin: 0 5px 5px 0 !important;
}

.lnb-license-submit-button {
  display: inline-block;
  margin-top: 10px;
  background-color: #fff;
  padding: 10px 1rem;
  cursor: pointer;
}

.lnb-license-x {
  font-weight: bold;
  font-size: 26px;
  color: red;
  vertical-align: middle;
  margin-right: 10px;
}

.lnb-license-check {
  margin-right: 5px;
}

.lnb-settings-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: -10px;
}

.lnb-settings-header span {
  margin-top: 10px;
}

.lnb-license-validate {
  border-bottom: 2px solid #999;
  padding-bottom: 3em;
  margin-bottom: 3em;
}

.lnb-license-validate strong {
  margin-left: 22px;
}

@media screen and (min-width: 625px) {
  .lnb-license-validation-message {
    margin-left: 35px !important;
  }
}
</style>

<!-- Licensing Addition -->
<div class="lnb-settings-header">
  <h1>Bulk Service Area Page Generator</h1><span>by Local Gladiator</span>
</div>
<form method="post">
  <div class="lnb-license-validate" style="border: 1px 1px solid #d6d6d6">
    <label style="display: block; margin-top: 30px;">
      <strong> Enter Your License Key Below to Enable this Product</strong>
    </label>
    <div style="display: inline-block;" class="lnb-license-input-wrapper">
      <?php if ($lic_active): ?>
      <img class="lnb-license-check" style="vertical-align: middle;" title=""
        src="<?php echo plugin_dir_url(__FILE__) . 'checkmark-24.png'; ?>" />
      <?php else: ?>
      <span class="lnb-license-x">X</span>
      <?php endif;?>
      <input name="lnb_license_validation" size="50" class="lnb-license-text-input" style="" type="text"
        value="<?php echo get_option('localglSAG'); ?>">
      </input>
      <input class="lnb-license-submit-button" type="submit" value="Save & Validate">
      <?php if ($lic_active): ?>
      <div class="lnb-license-validation-message" style="color: green;">
        This plugin license is validated and active.
      </div>
      <?php elseif ($lic_problem): ?>
      <div class="lnb-license-validation-message" style="color: red;">
        There is a problem with the licensing server. Please contact <a href="https://www.leadsnearby.com">LeadsNearby
        </a> for support.
      </div>
      <?php else: ?>
      <div class="lnb-license-validation-message" style="color: red;">
        This plugin is not active. Please enter a valid license above or contact
        <a target="_blank" href="https://www.leadsnearby.com">LeadsNearby </a> for support.
      </div>
      <?php endif;?>
    </div>
    <?php wp_nonce_field('1R6OqYQJLr_lnb_validate', 'JbxbMsiGnF_lnb_validate');?>
  </div>
</form>
<!-- End Licensing Addition -->

<?php
if (!is_object($body) || $body->status !== 'active' || empty($body) || $check !== true) {
      echo $msg;
      return;
    }
    ?>


<div id="lnb-service-area-form">
  <h2><?php echo _e('Auto Generated Page Contents Options'); ?></h2>
  <p>[lnb-city] and [lnb-state] shortcodes are accepted in all fields. Shortcodes will be transformed into your city
    name and state respectively.</p>
  <form id="bulk-creator-form" method="post" action="">
    <?php echo wp_nonce_field('lnb-sag'); ?>
    <table style="width:100%">
      <tbody>
        <tr>
          <td colspan="3">
            <label>Cities</label>
            <textarea required class="field" name="new_page[cities]"
              placeholder="List cities here, one per line"></textarea>
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
              <option value="VT">Vermont (VT)</option>
              <option value="VA">Virginia (VA)</option>
              <option value="WA">Washington (WA)</option>
              <option value="WV">West Virginia (WV)</option>
              <option value="WI">Wisconsin (WI)</option>
              <option value="WY">Alberta (AB)</option>
              <option value="WY">British Columbia (BC)</option>
              <option value="WY">Manitoba (MB)</option>
              <option value="WY">New Brunswick (NB)</option>
              <option value="WY">Newfoundland and Labrador (NL)</option>
              <option value="WY">Northwest Territories (NT)</option>
              <option value="WY">Nova Scotia (NS)</option>
              <option value="WY">Nunavut (NU)</option>
              <option value="WY">Ontario (ON)</option>
              <option value="WY">Prince Edward Island (PE)</option>
              <option value="WY">Quebec (QC)</option>
              <option value="WY">Saskatchewan (SK)</option>
              <option value="WY">Yukon (YT)</option>
            </select>
          </td>
        </tr>
        <tr>
          <td colspan="3">
            <label>Page Title</label>
            <input required type="text" class="field" name="post_fields[post_title]"
              placeholder="Enter page title template">
          </td>
        </tr>
        <tr>
          <td colspan="3">
            <label>Page URL</label>
            <input type="text" class="field" name="post_fields[post_name]"
              placeholder="Enter page url template (if different than title)">
          </td>
        </tr>
        <?php if ($page_parents || $page_templates || $theme_name = 'Avada' || $theme_parent = 'Avada'): ?>
        <tr>
          <?php if ($page_parents): ?>
          <td width="30%">
            <label>Page Parent</label>
            <select class="field" name="post_fields[post_parent]">
              <option disabled selected>-- Select Parent --</option>
              <option>No Parent</option>
              <?php foreach ($page_parents as $page): ?>
              <option value="<?php echo $page->ID; ?>"><?php echo $page->post_title; ?></option>
              <?php endforeach;?>
            </select>
          </td>
          <?php endif;?>
          <?php if ($page_templates): ?>
          <td width="30%">
            <label>Page Template</label>
            <select class="field" name="post_meta[_wp_page_template]">
              <option disabled selected>-- Select Page Template --</option>
              <option>Default Template</option>
              <?php foreach ($page_templates as $template_name => $template_filename): ?>
              <option value="<?php echo $template_filename; ?>"><?php echo $template_name; ?></option>
              <?php endforeach;?>
            </select>
          </td>
          <?php endif;?>
          <?php if ($theme_name = 'Avada' || $theme_parent = 'Avada'): ?>
          <td width="30%">
            <label>Sidebar</label>
            <select class="field" name="post_meta[sbg_selected_sidebar_replacement]">
              <option disabled selected>-- Select Sidebar --</option>
              <option>No Sidebar</option>
              <?php foreach ($wp_registered_sidebars as $sidebar) {?>
              <option value="<?php echo $sidebar['name']; ?>"><?php echo $sidebar['name']; ?></option>
              <?php }?>
            </select>
          </td>
          <?php endif;?>
        </tr>
        <?php endif;?>
        <tr>
          <td colspan="3">
            <label>Page Content</label>
            <!--<textarea class="tinymce-enabled" rows="15" cols="120" name="post_fields[post_content]" id="post_content"></textarea>-->
            <?php
wp_editor('', 'post_content', array(
      'wpautop'          => false,
      'media_buttons'    => true,
      'textarea_name'    => 'post_fields[post_content]',
      'textarea_rows'    => 10,
      'teeny'            => true,
      'tinymce'          => true,
      'drag_drop_upload' => true,
    ));
    ?>
          </td>
        </tr>
        <?php if (is_plugin_active('wordpress-seo/wp-seo.php')): ?>
        <tr>
          <td colspan="3">
            <input type="text" class="field" name="post_meta[_yoast_wpseo_title]" placeholder="Enter Yoast page title">
          </td>
        </tr>

        <tr>
          <td colspan="3">
            <textarea class="field" name="post_meta[_yoast_wpseo_metadesc]"
              placeholder="Enter Yoast meta description"></textarea>
          </td>
        </tr>
        <tr>
          <td colspan="3">
            <input type="text" class="field" name="post_meta[_yoast_wpseo_focuskw]"
              placeholder="Enter Yoast focus keyword">
          </td>
        </tr>
        <?php endif;?>
        <?php if (is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') && !is_plugin_active('wordpress-seo/wp-seo.php')): ?>
        <tr>
          <td colspan="3">
            <input type="text" class="field" name="post_meta[_aioseop_title]"
              placeholder="Enter All in One SEO page title">
          </td>
        </tr>
        <tr>
          <td colspan="3">
            <textarea class="field" name="post_meta[_aioseop_description]"
              placeholder="Enter All in One SEO neta description"></textarea>
          </td>
        </tr>
        <tr>
          <td colspan="3">
            <input type="text" class="field" name="post_meta[_aioseop_keywords]"
              placeholder="Enter All in One SEO focus keywords">
          </td>
        </tr>
        <?php endif;?>
      </tbody>
    </table>
    <input type="hidden" name="action" value="add_sa_pages">
    <p><input type="submit" value="<?php echo _e('Create Service Area Pages'); ?>"></p>
  </form>
</div>
<script>
function InsertNearbyNow() {
  var bulk_nearbynow_state = jQuery("#bulk_nearbynow_state").is(":checked");
  var bulk_nearbynow_city = jQuery("#display_nearbynow_city").is(":checked");
  var bulk_nearbynow_showmap = jQuery("#bulk_nearbynow_showmap").is(":checked");
  var bulk_nearbynow_reviewstart = jQuery("#bulk_nearbynow_reviewstart").is(":checked");
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
  window.send_to_editor("[serviceareareviewcombo city=\"" + city_nn + "\" state=\"" + state_nn + "\" showmap=\"" +
    showmap_nn + "\" reviewstart=\"" + reviewstart_nn + "\" reviewcount=\"" + reviewcount_nn + "\" checkinstart=\"" +
    checkinstart_nn + "\" checkincount=\"" + checkincount_nn + "\" zoomlevel=\"" + zoomlevel_nn +
    "\" reviewcityurl=\"" + reviewcityurl_nn + "\" mapsize=\"" + mapsize_nn + "\" mapscrollwheel=\"" +
    mapscrollwheel_nn + "\" fblike_nn=\"" + bulk_nearbynow_fblike + "\" fbcomment=\"" + fbcomment_nn + "\"]");
}
</script>

<div id="select_nearbynow" style="display:none;">
  <div class="wrap">
    <div>
      <div style="padding:15px 15px 0 15px;">
        <h3
          style="color:#5A5A5A!important; font-family:Georgia,Times New Roman,Times,serif!important; font-size:1.8em!important; font-weight:normal!important;">
          <?php _e("Insert NearbyNow shortcode", "bulk_nearbynow");?></h3>
      </div>
      <div style="padding:15px 15px 0 15px;">
        <!-- New Fields -->
        <input type="checkbox" id="bulk_nearbynow_state" /> <label
          for="bulk_nearbynow_state"><?php _e("State Shortcode", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="display_nearbynow_city" /> <label
          for="display_nearbynow_city"><?php _e("City Shortcode", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_showmap" /> <label
          for="bulk_nearbynow_showmap"><?php _e("Show Map", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_reviewstart" /> <label
          for="bulk_nearbynow_reviewstart"><?php _e("Review Start", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_reviewcount" /> <label
          for="bulk_nearbynow_reviewcount"><?php _e("Review Count", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_checkinstart" /> <label
          for="bulk_nearbynow_checkinstart"><?php _e("Checkin Start", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_checkincount" /> <label
          for="bulk_nearbynow_checkincount"><?php _e("Checkin Count", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_zoomlevel" /> <label
          for="bulk_nearbynow_zoomlevel"><?php _e("Zoom Level", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_reviewcityurl" /> <label
          for="bulk_nearbynow_reviewcityurl"><?php _e("Review URL", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_mapsize" /> <label
          for="bulk_nearbynow_mapsize"><?php _e("Map Size", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_mapscrollwheel" /> <label
          for="bulk_nearbynow_mapscrollwheel"><?php _e("Map Scroll Wheel", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_fblike" /> <label
          for="bulk_nearbynow_fblike"><?php _e("Facebook Like", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_fbcomment" /> <label
          for="bulk_nearbynow_fbcomment"><?php _e("Facebook Comment", "bulk_nearbynow");?></label><br />
        <input type="checkbox" id="bulk_nearbynow_serviceareaname" /> <label
          for="bulk_nearbynow_serviceareaname"><?php _e("Service Area Name", "bulk_nearbynow");?></label><br />
      </div>
      <div style="padding:15px;">
        <input type="button" class="button-primary" value="<?php _e("Insert NearbyNow Shortcode", "bulk_nearbynow");?>"
          onclick="InsertNearbyNow();" />&nbsp;&nbsp;&nbsp;
        <a class="button" style="color:#bbb;" href="#"
          onclick="tb_remove(); return false;"><?php _e("Cancel", "bulk_nearbynow");?></a>
      </div>
    </div>
  </div>
</div>
<?php
$html = ob_get_clean();
    echo $html;
  } // End Function LNB_settings_page

  public function add_nearbynow_button() {

    // do a version check for the new 3.5 UI
    $version = get_bloginfo('version');

    if ($version < 3.5) {
      // show button for v 3.4 and below
      $image_btn = plugins_url('/images/form-button.png', __FILE__);
      echo '<a href="#TB_inline?width=480&inlineId=select_nearbynow" class="thickbox" id="add_bulk_nearbynow" title="' . __("Add NearbyNow Shortcode", 'bulk_nearbynow') . '"><img src="' . $image_btn . '" alt="' . __("Add NearbyNow Shortcode", 'bulk_nearbynow') . '" /></a>';
    } else {
      // display button matching new UI
      echo '<a href="#TB_inline?width=480&inlineId=select_nearbynow" class="thickbox button gform_media_link" id="add_bulk_nearbynow" title="' . __("Add NearbyNow Shortcode", 'bulk_nearbynow') . '"><span style="padding-top: 3px;" class="dashicons dashicons-location-alt"></span> ' . __("Add NearbyNow", "bulk_nearbynow") . '</a>';
    }

  }

}

if (!is_admin()) {
  return;
}

$localglad_sag = ServiceAreaGenerator::getInstance();

add_action('admin_menu', [$localglad_sag, 'add_admin_page']);

//Action target that adds the "Insert NearbyNow" button to the post/page edit screen
add_action('media_buttons', [$localglad_sag, 'add_nearbynow_button'], 20);

?>