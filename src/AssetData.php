<?php

namespace Drupal\media_acquiadam;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\media_acquiadam\Entity\Asset;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Acquia DAM Asset Data service implementation.
 */
class AssetData implements AssetDataInterface, ContainerInjectionInterface {

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
  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
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

  /**
   * Check if the given asset is different than what is stored.
   *
   * @param \Drupal\media_acquiadam\Entity\Asset $asset
   *   The current version of the asset.
   *
   * @return bool
   *   TRUE if the given asset is a different version than what has been stored.
   */
  public function isUpdatedAsset(Asset $asset) {
    $latest_known_upload_date = $this->get($asset->id, 'file_upload_date');
    $actual_upload_date = strtotime($asset->file_upload_date);
    return $latest_known_upload_date !== $actual_upload_date;
  }

  /**
   * {@inheritdoc}
   */
  public function get($assetID = NULL, $name = NULL) {
    $query = $this->connection->select('acquiadam_assets_data', 'ad')->fields(
        'ad'
      );
    if (isset($assetID)) {
      $query->condition('asset_id', $assetID);
    }
    if (isset($name)) {
      $query->condition('name', $name);
    }
    $result = $query->execute();

    // A specific value for a specific asset ID was requested.
    if (isset($assetID) && isset($name)) {
      $result = $result->fetchAllAssoc('asset_id');
      if (isset($result[$assetID])) {
        return $result[$assetID]->serialized ?
          unserialize($result[$assetID]->value) : $result[$assetID]->value;
      }
      return NULL;
    }

    $return = [];

    // If only specific assets was requested.
    if (isset($assetID) || isset($name)) {
      $key = isset($assetID) ? 'name' : 'asset_id';

      foreach ($result as $record) {
        $return[$record->{$key}] = $record->serialized ?
          unserialize($record->value) : $record->value;
      }
      return $return;
    }

    // Everything was requested.
    foreach ($result as $record) {
      $return[$record->asset_id][$record->name] = $record->serialized ?
        unserialize($record->value) : $record->value;
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
    $this->connection->merge('acquiadam_assets_data')->keys(
      [
        'asset_id' => $assetID,
        'name' => $name,
      ]
    )->fields(
      [
        'value' => $value,
        'serialized' => $serialized,
      ]
    )->execute();
  }

}
