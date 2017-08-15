<?php

/**
 * Webdam_API helper class.
 *
 * Used to abstract out some specific reusable function implementations.
 *
 * @package Webdam
 */
class Webdam_API {

  /**
   * Get an Webdam_Client client instance.
   *
   * @return Webdam_Client
   *   A client object.
   */
  public static function getClient($key = NULL) {

    $client_mode = variable_get('media_webdam_client_mode', 'mixed');
    if (empty($key) && 'mixed' == $client_mode) {
      $key = 'webdam-server-auth';
    }
    elseif (empty($key) && 'background' == $client_mode) {
      $key = 'webdam-user-auth';
    }

    // Reuse the same client instance during the request.
    static $client;
    if (empty($client[$key])) {

      $oauth2_client = oauth2_client_load($key);
      $client[$key] = new Webdam_Client($oauth2_client);
    }

    return $client[$key];
  }

}
