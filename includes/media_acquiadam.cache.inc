<?php

/**
 * @file
 * Cache helpers and associated logic.
 */

/**
 * Cache Acquia DAM asset information locally.
 *
 * Calling for an already cached asset will update the cache.
 *
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The Acquia DAM asset to cache.
 */
function media_acquiadam_api_set_asset_cache(AcquiaDAM_Assets_AbstractAsset $asset) {

  $expiration = !empty($asset['expiration']['dateUnix']) ?
    $asset['expiration']['dateUnix'] :
    NULL;

  db_merge('acquiadam_asset_cache')
    ->key(['asset_id' => $asset['id']])
    ->updateFields([
      'changed' => REQUEST_TIME,
      'data' => serialize($asset->toArray()),
      'expiration' => $expiration,
    ])
    ->insertFields([
      'asset_id' => $asset['id'],
      'changed' => REQUEST_TIME,
      'created' => REQUEST_TIME,
      'data' => serialize($asset->toArray()),
      'expiration' => $expiration,
      'type' => $asset->getType(),
    ])
    ->execute();
}

/**
 * Get locally cached Acquia DAM asset information.
 *
 * @param int $assetId
 *   The Acquia DAM asset ID to get cached information for.
 *
 * @return AcquiaDAM_Assets_AbstractAsset|false
 *   The Acquia DAM asset or FALSE.
 */
function media_acquiadam_api_get_asset_cache($assetId) {

  $cache = &drupal_static(__FUNCTION__, []);
  if (empty($cache[$assetId])) {

    $item = media_acquiadam_api_get_assets_cache([$assetId]);

    $cache[$assetId] = !empty($item[$assetId]) ?
      $item[$assetId]->data :
      FALSE;
  }

  return $cache[$assetId];
}

/**
 * Retrieve multiple assets from the cache.
 *
 * @param array $assetIds
 *   An array of asset IDs to fetch from cache.
 *
 * @return array
 *   The array of asset data from cache, keyed by ID.
 */
function media_acquiadam_api_get_assets_cache(array $assetIds) {

  $query = db_select('acquiadam_asset_cache', 'c')
    ->addTag('acquiadam')
    ->addTag('damcache')
    ->fields('c', ['data', 'asset_id', 'type'])
    ->condition('c.asset_id', $assetIds)
    ->condition(db_or()
      ->condition('c.expiration', REQUEST_TIME, '>')
      ->isNull('c.expiration')
    )
    ->range(0, count($assetIds))
    ->orderBy('c.asset_id', 'ASC');

  $cache_expiration = variable_get('media_acquiadam_cache_expiration');
  if (!empty($cache_expiration)) {
    $query->where('(c.changed + :cache_expiration) > :request_time', [
      ':cache_expiration' => $cache_expiration * 60 * 1000,
      ':request_time' => REQUEST_TIME,
    ]);
  }

  $result = $query->execute()->fetchAllAssoc('asset_id');
  if (!empty($result)) {
    array_walk($result, function (&$item, $key) {
      $item->data = unserialize($item->data);
    });
  }

  return $result;
}

/**
 * Clears given asset IDs from the local cache.
 *
 * @param array $assetIds
 *   An array of asset IDs.
 */
function media_acquiadam_api_flush_item_cache(array $assetIds) {

  media_acquiadam_api_flush_image_cache($assetIds);

  db_delete('acquiadam_asset_cache')
    ->condition('asset_id', $assetIds)
    ->execute();
}

/**
 * Clear the cached external images of assets.
 *
 * This does not clear thumbnails for assets that are unused in the system.
 *
 * This helper function is in place so we can limit our flushing to just asset
 * images instead of everything the imagecache external might be used for.
 *
 * @param array|int $assetIds
 *   A asset ID or IDs to limit the flushing to.
 */
function media_acquiadam_api_flush_image_cache($assetIds = []) {

  if (!is_array($assetIds)) {
    $assetIds = [$assetIds];
  }

  module_load_include('inc', 'media_acquiadam', 'includes/media_acquiadam.image');

  // Go through each image style and clear out the cached asset folder for it.
  $styles = image_styles();
  foreach ($styles as $style) {
    foreach ($assetIds as $asset_id) {
      $uri = media_acquiadam_image_style_path($asset_id, $style, 'acquiadam', '');
      file_unmanaged_delete_recursive($uri);
    }
  }
}

/**
 * Flush expired asset cache.
 *
 * @return array
 *   An array of asset IDs that were cleared out.
 */
function media_acquiadam_api_flush_outdated_cache() {
  $assetIds = [];

  $query = db_select('acquiadam_asset_cache', 'c')
    ->fields('c', ['asset_id']);

  $or = db_or()
    ->condition('c.expiration', REQUEST_TIME, '<=');

  $cache_expiration = variable_get('media_acquiadam_cache_expiration');
  if ($cache_expiration) {
    // Clear out cache entries where the cache is stale.
    $or->where('(c.changed + :cache_expiration) <= :request_time', [
      ':cache_expiration' => $cache_expiration * 60 * 1000,
      ':request_time' => REQUEST_TIME,
    ]);
  }

  $unused_expiration = variable_get('media_acquiadam_unused_expiration');
  if ($unused_expiration) {
    // Clear out cache entries where no file is using the asset.
    $fq = db_select('file_managed', 'm')
      ->fields('m', ['acquiadam_id'])
      ->distinct();

    $or->condition(
      db_and()
        ->condition('c.asset_id', $fq, 'NOT IN')
        ->where('(c.changed + :unused_expiration) <= :request_time', [
          ':unused_expiration' => $unused_expiration * 60 * 1000,
          ':request_time' => REQUEST_TIME,
        ])
      );
  }

  $query->condition($or);

  $assetIds = $query->execute()->fetchCol();
  if (!empty($assetIds)) {
    media_acquiadam_api_flush_item_cache($assetIds);
  }

  return $assetIds;
}
