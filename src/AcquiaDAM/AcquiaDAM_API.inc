<?php

/**
 * AcquiaDAM_API helper class.
 *
 * Used to abstract out some specific reusable function implementations.
 *
 * @package AcquiaDAM
 */
class AcquiaDAM_API {

  /**
   * Get an AcquiaDAM_Client client instance.
   *
   * @return AcquiaDAM_Client
   *   A client object.
   */
  public static function getClient($key = NULL) {

    $client_mode = variable_get('media_acquiadam_client_mode', 'mixed');
    if (empty($key) && 'mixed' == $client_mode) {
      $key = 'acquiadam-server-auth';
    }
    elseif (empty($key) && 'background' == $client_mode) {
      $key = 'acquiadam-user-auth';
    }

    // Reuse the same client instance during the request.
    static $client;
    if (empty($client[$key])) {

      try {
        $oauth2_client = oauth2_client_load($key);
        $client[$key] = new AcquiaDAM_Client($oauth2_client);
      }
      catch (Exception $x) {
        watchdog_exception('AcquiaDAM_API', $x);
      }
    }

    return $client[$key];
  }

}
