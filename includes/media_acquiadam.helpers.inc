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

/**
 * Returns a Drupal image style URI for the given asset.
 *
 * Does not necessarily mean the asset is available to Drupal at this URI.
 *
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The asset to return a URI for.
 *
 * @return string
 *   The URI.
 */
function media_acquiadam_image_style_url(AcquiaDAM_Assets_AbstractAsset $asset) {
  return vsprintf('acquiadam://%s/%d', [
    $asset->getType(),
    $asset['id'],
  ]);
}
