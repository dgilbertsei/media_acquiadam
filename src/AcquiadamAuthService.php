<?php

namespace Drupal\media_acquiadam;

use Drupal\Core\Config\ImmutableConfig;

/**
 * The service to authenticate on Acquia DAM.
 *
 * @package Drupal\media_acquiadam
 */
class AcquiadamAuthService implements AcquiadamAuthServiceInterface {

  /**
   * The client_id used to identify Drupal module.
   *
   * @var string
   */
  const CLIENT_ID = 'd2df039fd77de48986459e68823a3380.app.widen.com';

  /**
   * The client_secret used to identify Drupal module.
   *
   * @var string
   */
  const CLIENT_SECRET = '9f2c7101ea3be48a9f98424f8d158afd1ef7daf4';

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
  public static function getConfig(): ImmutableConfig {
    return \Drupal::config('media_acquiadam.settings');
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
  public static function getEndpoint(string $method): string {
    // Generate the endpoint SSL URL of the given method.
    $config = self::getConfig();
    $acquiadam_domain = $config->get('domain');

    if (isset($acquiadam_domain)) {
      return 'https://' . $acquiadam_domain . '/api/rest/' . $method;
    }

    \Drupal::messenger()->addError(t('Acquia DAM endpoint must be configured'));

    return '';
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
  public static function generateAuthUrl(string $return_link): string {
    $config = self::getConfig();
    $acquiadam_domain = $config->get('domain');
    $client_id = self::CLIENT_ID;
    return $acquiadam_domain ? "https://$acquiadam_domain/allowaccess?client_id=$client_id&redirect_uri=$return_link" : '';

  }

  /**
   * Purge Acquia DAM authorization connection.
   *
   * @param string $access_token
   *   Acquiadam user token.
   *
   * @return bool
   *   Returns boolean based on access authorization.
   */
  public static function cancel(string $access_token): bool {
    if (empty($access_token)) {
      \Drupal::messenger()->addError(t('No token was provided.'));
      return FALSE;
    }

    $endpoint = self::getEndpoint('oauth/logout');

    // Initiate and process the response of the HTTP request.
    $response = \Drupal::httpClient()
      ->post($endpoint, [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);

    $http_status = $response->getStatusCode();

    // Display an error message if request fail.
    if ($http_status != '200') {
      $error_msg = t('Error Response from Authorization call [@status]', ['@status' => $http_status]);
      \Drupal::messenger()->addError($error_msg);

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Authenticates the user.
   *
   * @param string $auth_code
   *   The authorization code.
   *
   * @return object
   *   The response data of the authentication attempt.
   */
  public static function authenticate(string $auth_code): object {
    // Generate the token endpoint SSL URL of the request.
    $endpoint = self::getEndpoint('oauth/token');

    $data = [
      'authorization_code' => $auth_code,
      'grant_type' => 'authorization_code',
    ];

    // Initiate and process the response of the HTTP request.
    $response = \Drupal::httpClient()
      ->post($endpoint, [
        'auth' => [self::CLIENT_ID, self::CLIENT_SECRET],
        'body' => json_encode($data),
        'headers' => [
          'Content-Type' => 'application/json',
        ],
      ]);

    $http_status = $response->getStatusCode();

    // Display an error message if request fail.
    if ($http_status != '200') {
      $error_msg = t('Error Response from Authorization call [@status]', ['@status' => $http_status]);
      \Drupal::messenger()->addError($error_msg);
    }

    return json_decode($response->getBody());
  }

}
