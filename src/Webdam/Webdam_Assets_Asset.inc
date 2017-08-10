<?php

/**
 * Generic Webdam assets.
 *
 * @package Webdam
 */
class Webdam_Assets_Asset extends Webdam_Assets_AbstractAsset {

  /**
   * Return the API endpoint base.
   *
   * {@inheritDoc}
   *
   * @return string
   *   The base slug to use in API requests.
   */
  protected function getEndpointBase() {
    return 'assets';
  }

}
