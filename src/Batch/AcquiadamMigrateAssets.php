<?php

namespace Drupal\acquiadam\Batch;

/**
 * Class AcquiadamMigrateAssets.
 *
 * Sync existing media entities with DAM.
 */
class AcquiadamMigrateAssets {

  /**
   * Processes sync media entities.
   *
   * @param array $media_ids
   *   Acquiadam_asset media IDs.
   * @param array $sync_data
   *   Old asset ID and corresponding new Asset ID.
   * @param mixed $context
   *   Context.
   */
  public static function syncMedia(array $media_ids, array $sync_data, &$context) {
    $results = [];
    $message = \Drupal::translation()->translate('Syncing assets...');
    foreach ($media_ids as $id) {
      // Get Existing assets ID and entity ID.
      $query = \Drupal::database()->select('media__field_acquiadam_asset_id', 'asset')
        ->fields('asset', ['field_acquiadam_asset_id_value'])
        ->condition('entity_id', $id);
      $asset_id = $query->execute()->fetchField();
      // If this asset ID is available in the csv file.
      if (array_key_exists($asset_id, $sync_data)) {
        // Update field and custom table asset ID value with updated Asset ID.
        $results[] = self::updateTable($id, $asset_id, $sync_data[$asset_id]);
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
      $message = $translation->formatPlural(
        count($results),
        'One media is sync.', '@count media is sync.'
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
          \Drupal::logger('acquiadam')->error('Unable to sync media ID: @entity_id in @table. ', [
            '@entity_id' => $entity_id,
            '@table' => $table,
          ]);
        }
      }
    }

    return $data_updated;
  }

}
