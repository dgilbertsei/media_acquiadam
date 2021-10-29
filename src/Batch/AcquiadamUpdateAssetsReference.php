<?php

namespace Drupal\media_acquiadam\Batch;

/**
 * Class AcquiadamUpdateAssetsReference.
 *
 * Sync existing media entities with DAM.
 */
class AcquiadamUpdateAssetsReference {

  /**
   * Processes sync media entities.
   *
   * @param array $sync_data
   *   Old asset ID and corresponding new Asset ID.
   * @param mixed $context
   *   Context.
   */
  public static function syncMedia(array $sync_data, &$context) {
    $results = [];
    $message = \Drupal::translation()->translate('Syncing assets...');

    // Build an associative array of all the existing AcquiaDam media. The key
    // is the asset id (from AcquiaDam), the value is the media id (Drupal).
    $asset_id_fields = acquia_acquiadam_get_bundle_asset_id_fields();
    $ids = [];
    foreach ($asset_id_fields as $bundle => $field) {
      $query = \Drupal::database()->select('media__' . $field, 'asset')
        ->fields('asset', ['entity_id', $field . '_value'])
        ->condition('bundle', $bundle);
      $ids = array_merge($ids, $query->execute()->fetchAllKeyed(1, 0));
    }

    foreach ($ids as $asset_id => $entity_id) {
      if (array_key_exists($asset_id, $sync_data)) {
        $results[] = self::updateTable($entity_id, $asset_id, $sync_data[$asset_id]);
      }
    }

    $context['message'] = $message;
    $context['results'] = $results;
  }

  /**
   * Media Sync finish process.
   */
  public static function finishBatchOperation($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    $translation = \Drupal::translation();
    if ($success) {
      $message = $translation->translate(
        'Medias have been migrated.'
      );
      $messenger->addStatus($message);
      drupal_flush_all_caches();
    }
    else {
      $message = $translation->translate('Finished with an error.');
      $messenger->addError($message);
    }
  }

  /**
   * Update New Asset ID in field and custom table.
   *
   * @param string $entity_id
   *   Media Entity ID.
   * @param string $old_asset_id
   *   Media old Asset ID.
   * @param string $new_asset_id
   *   Media new Asset ID.
   *
   * @return array
   *   Results contain operation count.
   */
  public static function updateTable(string $entity_id, string $old_asset_id, string $new_asset_id) {
    $tables = [
      'media__field_acquiadam_asset_id',
      'media_revision__field_acquiadam_asset_id',
      'acquiadam_assets_data',
    ];
    $database = \Drupal::database();
    $data_updated = FALSE;
    // Update field table, field revision table and custom table asset ID field.
    foreach ($tables as $table) {
      if ($database->schema()->tableExists($table)) {
        $query = $database->update($table);
        if ($table === 'acquiadam_assets_data') {
          $query->fields(['asset_id' => $new_asset_id]);
          $query->condition('asset_id', $old_asset_id);
        }
        else {
          $query->fields(['field_acquiadam_asset_id_value' => $new_asset_id]);
          $query->condition('entity_id', $entity_id);
        }
        try {
          $query->execute();
          $data_updated = TRUE;
        }
        catch (\Exception $e) {
          // Logs an error if asset ID is failed to update in table.
          \Drupal::logger('media_acquiadam')->error('Unable to sync media ID: @entity_id in @table. ', [
            '@entity_id' => $entity_id,
            '@table' => $table,
          ]);
        }
      }
    }

    return $data_updated;
  }

}
