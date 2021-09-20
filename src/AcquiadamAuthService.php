<?php

namespace Drupal\acquiadam;

/**
 * Class AcquiadamAuthService.
 *
 * @package Drupal\acquiadam
 */
class AcquiadamAuthService implements AcquiadamAuthServiceInterface {

  /**
   * Constructor.
   */
  public function __construct() {
  }

  /**
   * Returns acquiadam setting config where it stores the authentication data.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   An immutable configuration object.
   */
  public static function getConfig() {
    return \Drupal::config('acquiadam.settings');
  }

  /**
   * Gets endpoint or path.
   *
   * @param string $method
   *   The method to be called in the API.
   *
   * @return string
   *   The absolute path of the endpoint of the method.
   */
  public static function getEndpoint($method) {
    // Generate the endpoint SSL URL of the given method.
    $config = self::getConfig();
    $acquiadam_domain = $config->get('domain');

    if (isset($acquiadam_domain)) {
      return 'https://' . $acquiadam_domain . '/api/rest/' . $method;
    }

    \Drupal::messenger()->addError(t('Acquia DAM endpoint must be configured'));
  }

  /**
   * Provides the authorization link with Acquia DAM.
   *
   * @param string $return_link
   *   The url where it should redirect after the authentication.
   *
   * @return string
   *   The absolute URL used for authorization.
   */
  public static function generateAuthUrl($return_link) {
    $config = self::getConfig();
    $acquiadam_domain = $config->get('domain');
    $client_registration = $config->get('client_registration');

    return 'https://' . $acquiadam_domain . '/allowaccess?client_id=' . $client_registration . '&redirect_uri=' . $return_link;
  }

}
