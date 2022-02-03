<?php

namespace localgl\plugins\serviceareagenerator;

if (!defined('ABSPATH')) {
  exit;
}
// Exit if accessed directly

class ConfirmVal {

  public static $secret_key = '61511cad7ea2c5.17132492';
  public static $server_url = 'https://licensing.gladsvs.com';
  public static $item_ref = 'Bulk Service Area Page Generator';

  public static function val($license) {
    $params = array(
      'slm_action'  => 'slm_check',
      'secret_key'  => self::$secret_key,
      'license_key' => $license,
    );

    $response = wp_remote_get(add_query_arg($params, self::$server_url), array('timeout' => 10, 'sslverify' => false));

    return $response;

  }

  public static function activate($license) {

    $urlparts = parse_url(home_url());
    $domain = $urlparts['host'];

    $params = array(
      'secret_key'        => '61511cad7ea2c5.17132492',
      'slm_action'        => 'slm_activate',
      'license_key'       => $license,
      'registered_domain' => $domain,
    );

    $response = wp_remote_get(add_query_arg($params, self::$server_url), array('timeout' => 10, 'sslverify' => false));

    return $response;
  }
}