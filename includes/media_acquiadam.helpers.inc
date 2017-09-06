<?php

/**
 * @file
 * Helper functions for working with AcquiaDAM within Drupal.
 */

/**
 * Create a local file entity for an AcquiaDAM asset.
 *
 * @param AcquiaDAM_Assets_Asset $asset
 *   The AcquiaDAM asset to create a file entity for.
 * @param bool $ignoreExisting
 *   By default we return an existing file if one was found. Set this TRUE to
 *   force a new file to be created.
 *
 * @return object|bool
 *   The created file entity or FALSE on failure.
 */
function media_acquiadam_api_create_acquiadam_file(AcquiaDAM_Assets_Asset $asset, $ignoreExisting = FALSE) {

  global $user;

  if (!$asset instanceof AcquiaDAM_Assets_Asset) {

    return FALSE;
  }

  $uri = media_acquiadam_api_asset_to_uri($asset);
  // Reuse an existing file whenever possible.
  $file = file_uri_to_object($uri, TRUE);

  // We got a file object for a new file instead of an existing one.
  if (!empty($file) && empty($file->fid)) {
    media_acquiadam_api_set_asset_cache($asset);
    $file->filename = drupal_basename($asset['name']);
    $file->acquiadam_id = $asset['id'];
    file_save($file);
  }

  return empty($file->fid) ? FALSE : $file;
}

/**
 * Asset/folder get helper that can load and store assets to cache.
 *
 * @param string $type
 *   The type of asset to try and load (folder, asset).
 * @param int|array $id
 *   An asset ID or the asset data.
 * @param array $depends
 *   An array of dependencies to pass to the class constructors.
 * @param bool $skipCache
 *   TRUE to skip the cache mechanism.
 *
 * @return AcquiaDAM_Assets_AbstractAsset|false
 *   A AcquiaDAM_Assets_AbstractAsset object or FALSE.
 */
function media_acquiadam_api_get_helper($type, $id, array $depends = [], $skipCache = FALSE) {

  // We only want to try and cache when we are given an id instead of an array.
  // We also need to ignore caching the root folder.
  $use_cache = empty($skipCache) && is_numeric($id) && $id != AcquiaDAM_Assets_Folder::ROOT_FOLDER_ID;
  if ($use_cache) {
    $cached_item = media_acquiadam_api_get_asset_cache($id);
  }

  $create_arg = empty($cached_item) ? $id : $cached_item;

  $result = FALSE;
  try {
    if ('folder' == $type) {
      $result = new AcquiaDAM_Assets_Folder($create_arg, $depends);
    }
    elseif ('asset' == $type) {
      $result = new AcquiaDAM_Assets_Asset($create_arg, $depends);
    }
    else {
      watchdog('media_acquiadam', 'Unhandled asset type: @type', ['@type' => $type], WATCHDOG_DEBUG);
    }
  }
  catch (Exception $x) {
    watchdog_exception('media_acquiadam', $x);
  }

  $can_cache_result = !empty($result) && empty($cached_item);
  if ($use_cache && $can_cache_result) {
    media_acquiadam_api_set_asset_cache($result);
  }

  return $result;
}

/**
 * Load a Folder asset.
 *
 * @param int|array $id
 *   An asset ID or the asset data.
 * @param array $depends
 *   An array of dependencies to pass to the class constructors.
 * @param bool $skipCache
 *   TRUE to skip the cache mechanism.
 *
 * @return AcquiaDAM_Assets_AbstractAsset|false
 *   A AcquiaDAM_Assets_AbstractAsset object or FALSE.
 */
function media_acquiadam_api_get_folder($id, array $depends = [], $skipCache = FALSE) {
  return media_acquiadam_api_get_helper('folder', $id, $depends, $skipCache);
}

/**
 * Load an Asset.
 *
 * @param int|array $id
 *   An asset ID or the asset data.
 * @param array $depends
 *   An array of dependencies to pass to the class constructors.
 * @param bool $skipCache
 *   TRUE to skip the cache mechanism.
 *
 * @return AcquiaDAM_Assets_AbstractAsset|false
 *   A AcquiaDAM_Assets_AbstractAsset object or FALSE.
 */
function media_acquiadam_api_get_asset($id, array $depends = [], $skipCache = FALSE) {
  return media_acquiadam_api_get_helper('asset', $id, $depends, $skipCache);
}

/**
 * Attempts to load an asset by ID without knowing the type.
 *
 * This function should be avoided when possible as it can cause extra API
 * requests.
 *
 * @param int|array $id
 *   An asset ID or the asset data.
 * @param array $depends
 *   An array of dependencies to pass to the class constructors.
 * @param bool $skipCache
 *   TRUE to skip the cache mechanism.
 *
 * @return AcquiaDAM_Assets_AbstractAsset|false
 *   A AcquiaDAM_Assets_AbstractAsset object or FALSE.
 */
function media_acquiadam_api_get_unknown($id, array $depends = [], $skipCache = FALSE) {

  if (is_array($id) && !empty($id['type'])) {
    if ('asset' == $id['type']) {
      return media_acquiadam_api_get_asset($id, $depends, $skipCache);
    }
    elseif ('asset' == $id['type']) {
      return media_acquiadam_api_get_folder($id, $depends, $skipCache);
    }
  }

  $asset = media_acquiadam_api_get_asset($id, $depends, $skipCache);
  if (!empty($asset)) {
    return $asset;
  }

  $asset = media_acquiadam_api_get_folder($id, $depends, $skipCache);
  if (!empty($asset)) {
    return $asset;
  }

  return FALSE;
}

/**
 * Cache AcquiaDAM asset information locally.
 *
 * Calling for an already cached asset will update the cache.
 *
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The AcquiaDAM asset to cache.
 */
function media_acquiadam_api_set_asset_cache(AcquiaDAM_Assets_AbstractAsset $asset) {
  db_merge('acquiadam_asset_cache')
    ->key(['asset_id' => $asset['id']])
    ->updateFields([
      'data' => serialize($asset->toArray()),
      'changed' => REQUEST_TIME,
    ])
    ->insertFields([
      'asset_id' => $asset['id'],
      'type' => $asset->getType(),
      'data' => serialize($asset->toArray()),
      'changed' => REQUEST_TIME,
      'created' => REQUEST_TIME,
    ])
    ->execute();
}

/**
 * Get locally cached AcquiaDAM asset information.
 *
 * @param int $assetId
 *   The AcquiaDAM asset ID to get cached information for.
 *
 * @return AcquiaDAM_Assets_AbstractAsset|false
 *   The AcquiaDAM asset or FALSE.
 */
function media_acquiadam_api_get_asset_cache($assetId) {

  $cache = &drupal_static(__FUNCTION__, []);
  if (empty($cache[$assetId])) {

    $result = db_select('acquiadam_asset_cache', 'c')
      ->addTag('acquiadam')
      ->fields('c', ['data'])
      ->condition('c.asset_id', intval($assetId))
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $cache[$assetId] = !empty($result) ?
      unserialize($result) :
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

  $result = db_select('acquiadam_asset_cache', 'c')
    ->addTag('acquiadam')
    ->fields('c', ['data', 'asset_id', 'type'])
    ->condition('c.asset_id', $assetIds)
    ->execute()
    ->fetchAllAssoc('asset_id');

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

  // We don't use REQUEST_TIME because it won't be set with drush.
  $time = time();

  $query = db_select('acquiadam_asset_cache', 'c')
    ->fields('c', ['asset_id']);

  $or = db_or();

  $cache_expiration = variable_get('media_acquiadam_cache_expiration');
  if ($cache_expiration) {
    // Clear out cache entries where the cache is stale.
    $or->condition('c.changed', $cache_expiration + $time, '<=');
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
        ->condition('c.changed', $unused_expiration + $time, '<=')
      );
  }

  $query->condition($or);

  $assetIds = $query->execute()->fetchCol();
  if (!empty($assetIds)) {
    media_acquiadam_api_flush_item_cache($assetIds);
  }

  return $assetIds;
}

/**
 * Get managed files that have associated asset IDs.
 *
 * @param array $assetIds
 *   The asset IDs to load files for.
 *
 * @return array
 *   Any matching files from the managed files table.
 */
function media_acquiadam_api_get_files_by_asset_ids(array $assetIds) {
  $assetIds = array_map('intval', $assetIds);
  $assetIds = array_filter($assetIds);

  $result = db_select('file_managed', 'm')
    ->addTag('acquiadam')
    ->fields('m', ['fid', 'acquiadam_id'])
    ->condition('m.acquiadam_id', $assetIds)
    ->execute()
    ->fetchAll();

  return $result;
}

/**
 * Get a managed file that is associated with the given asset ID.
 *
 * @param int $assetId
 *   The asset ID to load a file for.
 *
 * @return array|false
 *   A matching file or FALSE.
 */
function media_acquiadam_api_get_file_by_asset_id($assetId) {
  $result = media_acquiadam_api_get_files_by_asset_ids([$assetId]);
  return empty($result) ? FALSE : current($result);
}

/**
 * Returns a Drupal URI for the given asset.
 *
 * Does not necessarily mean the asset is available to Drupal at this URI.
 *
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The asset to return a URI for.
 *
 * @return string
 *   The URI.
 */
function media_acquiadam_api_asset_to_uri(AcquiaDAM_Assets_AbstractAsset $asset) {

  $uri = sprintf('acquiadam://%d', $asset['id']);
  if (!empty($asset['filetype'])) {
    $uri = sprintf('%s.%s', $uri, $asset['filetype']);
  }

  return $uri;
}
