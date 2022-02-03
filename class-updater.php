<?php

namespace localgl\plugins\serviceareagenerator;

if (!defined('ABSPATH')) {
  exit;
}
// Exit if accessed directly

class Updater {

  private $plugin_info_url = 'https://licensing.gladsvs.com/plugin-info/';
  private $download_url = 'https://licensing.gladsvs.com/plugin-download/';
  private $domain;
  private $version;

  public function __construct() {
    $urlparts = parse_url(home_url());
    $this->domain = $urlparts['host'];

    add_filter('plugins_api', array($this, 'get_plugin_info'), 9, 3);
    add_filter('pre_set_site_transient_update_plugins', array($this, 'push_update'));
  }

  public function get_current_plugin() {

    $plugins = get_plugins();

    if (!empty($plugins[SAG_PLUGIN_FILE_NAME])) {
      $this->version = $plugins[SAG_PLUGIN_FILE_NAME]['Version'];
      return $plugins[SAG_PLUGIN_FILE_NAME];
    } else {
      return new WP_ERROR('Plugin File Not Matching');
    }

  }

  public function get_plugin_info($result, $action, $args) {

    if ($action !== 'plugin_information') {
      return;
    }

    if ($args->slug !== SAG_PLUGIN_SLUG) {
      return;
    }

    $params = array(
      'secret_key'        => '61511cad7ea2c5.17132492',
      'license_key'       => get_option('localglSAG'),
      'registered_domain' => $this->domain,
      'plugin'            => SAG_PLUGIN_FILE_NAME,
    );

    $current_plugin_data = $this->get_current_plugin();

    $response = wp_remote_get(add_query_arg($params, $this->plugin_info_url), array('timeout' => 10, 'sslverify' => false));
    if (is_wp_error($response)) {
      return;
    }

    $resp_obj = json_decode($response['body']);

    $result = new \stdClass();
    $result->name = $resp_obj->Name;
    $result->slug = SAG_PLUGIN_SLUG;
    $result->author = $resp_obj->Author;
    $result->description = $resp_obj->Description;
    $result->author_profile = $resp_obj->AuthorURI;
    $result->version = $resp_obj->Version;
    $result->tested = '5.8.3';
    $result->requires = $resp_obj->RequiresWP;
    $result->requires_php = $resp_obj->RequiresPHP;
    $result->download_link = $this->download_url;
    $result->trunk = $this->download_url;
    $result->last_updated = '2021-02-01';
    $result->homepage = $resp_obj->PluginURI;

    $result->sections = array(
      'description' => "Extends Nearby Now, providing new ways to display Nearby Now reviews on your website.",
      // 'installation' =>  $resp_obj->sections->installation,
      // 'changelog'    =>  $resp_obj->sections->changelog,
    );

    return $result;

  }

  public function push_update($transient) {

    if (empty($transient->checked)) {
      return $transient;
    }

    $params = array(
      'secret_key'        => '61511cad7ea2c5.17132492',
      'license_key'       => get_option('localglSAG'),
      'registered_domain' => $this->domain,
      'plugin'            => SAG_PLUGIN_FILE_NAME,
    );

    $response = wp_remote_get(add_query_arg($params, $this->plugin_info_url), array('timeout' => 10, 'sslverify' => false));
    $resp_obj = json_decode($response['body']);

    $this->get_current_plugin(); // Set $this->version

    if ($resp_obj
      && version_compare($this->version, $resp_obj->Version, '<')
      && version_compare($resp_obj->RequiresWP, get_bloginfo('version'), '<')
      && version_compare($resp_obj->RequiresPHP, PHP_VERSION, '<')) {

      $params['plugin'] = SAG_PLUGIN_SLUG;
      $this->download_url = $this->download_url . '?' . http_build_query($params);

      $result = new \stdClass();
      $result->slug = SAG_PLUGIN_SLUG;
      $result->plugin = SAG_PLUGIN_FILE_NAME;
      $result->new_version = $resp_obj->Version;
      $result->tested = '5.8.3';
      $result->package = $this->download_url;
      $transient->response[$result->plugin] = $result;
    }

    return $transient;
  }

}