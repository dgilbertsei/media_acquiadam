<?php

namespace Drupal\acquiadam;

/**
 * Interface AcquiadamAuthServiceInterface.
 *
 * @package Drupal\acquiadam
 */
interface AcquiadamAuthServiceInterface {

  /**
   * Returns acquiadam setting config where it stores the authentication data.
   */
  public static function getConfig();

  /**
   * Gets endpoint or path.
   *
   * @param string $method
   *   The method to be called in the API.
   *
   * @return string
   *   The absolute path of the endpoint of the method.
   */
  public static function getEndpoint($method);

  /**
   * Provides the authorization link with Acquia DAM.
   *
   * @param string $return_link
   *   The url where it should redirect after the authentication.
   *
   * @return string
   *   The absolute URL used for authorization.
   */
  public static function generateAuthUrl($return_link);


  /**
   * Authenticates the user.
   *
   * @param string $auth_code
   *   The authorization code.
   *
   * @return array
   *   The response data of the authentication attempt.
   */
  public static function authenticate($auth_code);

}
