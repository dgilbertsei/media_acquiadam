<?php

namespace Drupal\media_acquiadam;

/**
 * Interface AcquiadamInterface.
 *
 * Defines the Acquia dam interface.
 */
interface AcquiadamInterface {

  /**
   * Passes method calls through to the DAM client object.
   *
   * @param string $name
   *   The name of the method to call.
   * @param array $arguments
   *   An array of arguments.
   *
   * @return mixed
   *   Returns whatever the dam client returns.
   */
  public function __call($name, array $arguments);

}
