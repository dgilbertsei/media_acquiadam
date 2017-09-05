<?php

/**
 * @file
 * Helper functions for working with Webdam within Drupal.
 */

/**
 * Create a local file entity for a Webdam asset.
 *
 * @param Webdam_Assets_Asset $asset
 *   The Webdam asset to create a file entity for.
 * @param bool $ignoreExisting
 *   By default we return an existing file if one was found. Set this TRUE to
 *   force a new file to be created.
 *
 * @return object|bool
 *   The created file entity or FALSE on failure.
 */
function media_webdam_api_create_webdam_file(Webdam_Assets_Asset $asset, $ignoreExisting = FALSE) {

  global $user;

  if (!$asset instanceof Webdam_Assets_Asset) {

    return FALSE;
  }

  $uri = sprintf('webdam://%d.%s', $asset['id'], $asset['filetype']);
  // Reuse an existing file whenever possible.
  $file = file_uri_to_object($uri, TRUE);

  // We got a file object for a new file instead of an existing one.
  if (!empty($file) && empty($file->fid)) {
    media_webdam_api_set_asset_cache($asset);
    $file->filename = drupal_basename($asset['name']);
    $file->webdam_id = $asset['id'];
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
 * @return Webdam_Assets_AbstractAsset|false
 *   A Webdam_Assets_AbstractAsset object or FALSE.
 */
function media_webdam_api_get_helper($type, $id, array $depends = [], $skipCache = FALSE) {

  // We only want to try and cache when we are given an id instead of an array.
  // We also need to ignore caching the root folder.
  $use_cache = empty($skipCache) && is_numeric($id) && $id != Webdam_Assets_Folder::ROOT_FOLDER_ID;
  if ($use_cache) {
    $cached_item = media_webdam_api_get_asset_cache($id);
  }

  $create_arg = empty($cached_item) ? $id : $cached_item;

  $result = FALSE;
  try {
    if ('folder' == $type) {
      $result = new Webdam_Assets_Folder($create_arg, $depends);
    }
    elseif ('asset' == $type) {
      $result = new Webdam_Assets_Asset($create_arg, $depends);
    }
    else {
      watchdog('media_webdam', 'Unhandled asset type: @type', ['@type' => $type], WATCHDOG_DEBUG);
    }
  }
  catch (Exception $x) {
    watchdog_exception('media_webdam', $x);
  }

  $can_cache_result = !empty($result) && empty($cached_item);
  if ($use_cache && $can_cache_result) {
    media_webdam_api_set_asset_cache($result);
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
 * @return Webdam_Assets_AbstractAsset|false
 *   A Webdam_Assets_AbstractAsset object or FALSE.
 */
function media_webdam_api_get_folder($id, array $depends = [], $skipCache = FALSE) {
  return media_webdam_api_get_helper('folder', $id, $depends, $skipCache);
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
 * @return Webdam_Assets_AbstractAsset|false
 *   A Webdam_Assets_AbstractAsset object or FALSE.
 */
function media_webdam_api_get_asset($id, array $depends = [], $skipCache = FALSE) {
  return media_webdam_api_get_helper('asset', $id, $depends, $skipCache);
}

/**
 * Cache Webdam asset information locally.
 *
 * Calling for an already cached asset will update the cache.
 *
 * @param Webdam_Assets_AbstractAsset $asset
 *   The Webdam asset to cache.
 */
function media_webdam_api_set_asset_cache(Webdam_Assets_AbstractAsset $asset) {
  db_merge('webdam_asset_cache')
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
 * Get locally cached Webdam asset information.
 *
 * @param int $assetId
 *   The Webdam asset ID to get cached information for.
 *
 * @return Webdam_Assets_AbstractAsset|false
 *   The Webdam asset or FALSE.
 */
function media_webdam_api_get_asset_cache($assetId) {

  $cache = &drupal_static(__FUNCTION__, []);
  if (empty($cache[$assetId])) {

    $result = db_select('webdam_asset_cache', 'c')
      ->addTag('webdam')
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
function media_webdam_api_get_assets_cache(array $assetIds) {

  $result = db_select('webdam_asset_cache', 'c')
    ->addTag('webdam')
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
function media_webdam_api_clear_from_cache(array $assetIds) {

  media_webdam_api_flush_image_cache($assetIds);

  db_delete('webdam_asset_cache')
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
function media_webdam_api_flush_image_cache($assetIds = []) {

  if (!module_exists('imagecache_external')) {
    return;
  }

  $query = db_select('file_managed', 'm')
    ->fields('m', ['uri'])
    ->condition('m.uri', db_like('webdam://') . '%', 'LIKE')
    ->distinct();

  if (!empty($assetIds)) {
    $query->condition('m.webdam_id', $assetIds);
  }

  $records = $query->execute();
  foreach ($records as $record) {
    $uri = file_create_url($record->uri);
    // We only need to worry about asset URLs that translated to a remote URL.
    if (!empty($uri) && url_is_external($uri)) {
      $path = imagecache_external_generate_path($uri);
      if (!empty($path)) {
        file_unmanaged_delete_recursive($path);
      }
    }
  }
}

/**
 * Flush expired asset cache.
 *
 * @return array
 *   An array of asset IDs that were cleared out.
 */
function media_webdam_api_flush_outdated_cache() {
  $assetIds = [];

  // We don't use REQUEST_TIME because it won't be set with drush.
  $time = time();

  $query = db_select('webdam_asset_cache', 'c')
    ->fields('c', ['asset_id']);

  $or = db_or();

  $cache_expiration = variable_get('media_webdam_cache_expiration');
  if ($cache_expiration) {
    // Clear out cache entries where the cache is stale.
    $or->condition('c.changed', $cache_expiration + $time, '<=');
  }

  $unused_expiration = variable_get('media_webdam_unused_expiration');
  if ($unused_expiration) {
    // Clear out cache entries where no file is using the asset.
    $fq = db_select('file_managed', 'm')
      ->fields('m', ['webdam_id'])
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
    media_webdam_api_clear_from_cache($assetIds);
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
function media_webdam_api_get_files_by_asset_ids(array $assetIds) {
  $assetIds = array_map('intval', $assetIds);
  $assetIds = array_filter($assetIds);

  $result = db_select('file_managed', 'm')
    ->addTag('webdam')
    ->fields('m', ['fid', 'webdam_id'])
    ->condition('m.webdam_id', $assetIds)
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
function media_webdam_api_get_file_by_asset_id($assetId) {
  $result = media_webdam_api_get_files_by_asset_ids([$assetId]);
  return empty($result) ? FALSE : current($result);
}
