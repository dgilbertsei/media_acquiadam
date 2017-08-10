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
  public static function getClient() {

    // Reuse the same client instance during the request.
    static $client;
    if (empty($client)) {

      $oauth2_client = oauth2_client_load('webdam-server-auth');
      $client = new Webdam_Client($oauth2_client);
    }

    return $client;
  }

}
