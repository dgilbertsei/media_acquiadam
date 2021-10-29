<?php

namespace Drupal\media_acquiadam\Entity;

/**
 * Provides an interface that all entity classes must implement.
 */
interface EntityInterface {

  /**
   * Create new instance of the Entity given a JSON object from Acquia DAM API.
   *
   * @param string|object $json
   *   Either a JSON string or a json_decode()'d object.
   *
   * @return EntityInterface
   *   An instance of whatever class this method is being called on.
   */
  public static function fromJson($json);

}
