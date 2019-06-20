<?php

namespace Drupal\media_acquiadam;

use cweagans\webdam\Entity\Asset;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
   * Check if the given asset is newer than what is stored.
   *
   * @param \cweagans\webdam\Entity\Asset $asset
   *   The current version of the asset.
   * @param bool $saveUpdatedVersion
   *   TRUE to save the new version (if newer than the existing).
   *
   * @return bool
   *   TRUE if the given asset is a newer version than what has been stored.
   */
  public function isUpdatedAsset(Asset $asset, $saveUpdatedVersion = TRUE) {
    $current_version = intval($this->get($asset->id, 'version'));
    $new_version = intval($asset->version);
    $is_updated_version = $new_version > 1 && $new_version != $current_version;
    if ($is_updated_version && $saveUpdatedVersion) {
      // Track the new version for future reference.
      $this->set($asset->id, 'version', $new_version);
    }

    return $is_updated_version;
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
    // All values for a given asset ID were requested.
    if (isset($assetID)) {
      foreach ($result as $record) {
        $return[$record->name] = $record->serialized ?
          unserialize($record->value) : $record->value;
      }
      return $return;
    }

    // All asset IDs for a given value were requested.
    if (isset($name)) {
      foreach ($result as $record) {
        $return[$record->asset_id] = $record->serialized ?
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
