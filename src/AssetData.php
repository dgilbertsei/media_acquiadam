<?php
/**
 * @file
 * Acquia DAM Asset Data service implementation.
 */

namespace Drupal\media_acquiadam;

use Drupal\Core\Database\Connection;

/**
 * Defines the asset data service.
 */
class AssetData implements AssetDataInterface {

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new asset data service.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function get($assetID, $name = NULL) {
    $query = $this->connection->select('acquiadam_assets_data', 'ad')
      ->fields('ad')
      ->condition('asset_id', $assetID);
    if (isset($name)) {
      $query->condition('name', $name);
    }
    $result = $query->execute();

    // If $name was provided we should only return the specific value instead
    // of an array with just the value in it.
    if (isset($name)) {
      $result = $result->fetchAllAssoc('name');
      if (isset($result[$name])) {
        return $result[$name]->serialized ? unserialize($result[$name]->value) : $result[$name]->value;
      }
      return NULL;
    }

    $return = [];
    foreach ($result as $record) {
      $return[$record->name] = ($record->serialized ? unserialize($record->value) : $record->value);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function set($assetID, $name, $value) {
    $serialized = (int) !is_scalar($value);
    if ($serialized) {
      $value = serialize($value);
    }
    $this->connection->merge('acquiadam_assets_data')
      ->keys([
        'asset_id' => $assetID,
        'name' => $name,
      ])
      ->fields([
        'value' => $value,
        'serialized' => $serialized,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function delete($assetID = NULL, $name = NULL) {
    $query = $this->connection->delete('acquiadam_assets_data');
    // Cast scalars to array so we can consistently use an IN condition.
    if (isset($assetID)) {
      $query->condition('asset_id', (array) $assetID, 'IN');
    }
    if (isset($name)) {
      $query->condition('name', (array) $name, 'IN');
    }
    $query->execute();
  }

}
