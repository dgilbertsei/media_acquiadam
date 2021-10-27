<?php

namespace Drupal\media_acquiadam;

/**
 * The interface for a service to handle authentication on Acquia DAM.
 *
 * @package Drupal\media_acquiadam
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
  public static function getEndpoint(string $method): string;

  /**
   * Provides the authorization link with Acquia DAM.
   *
   * @param string $return_link
   *   The url where it should redirect after the authentication.
   *
   * @return string
   *   The absolute URL used for authorization.
   */
  public static function generateAuthUrl(string $return_link): string;

  /**
   * Purge Acquia DAM authorization connection.
   *
   * @param string $access_token
   *   Acquiadam user token.
   *
   * @return bool
   *   Returns a boolean based on authorization.
   */
  public static function cancel(string $access_token): bool;

  /**
   * Authenticates the user.
   *
   * @param string $auth_code
   *   The authorization code.
   *
   * @return object
   *   The response data of the authentication attempt.
   */
  public static function authenticate(string $auth_code): object;

}
