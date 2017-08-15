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

  if (empty($ignoreExisting)) {

    $result = media_webdam_api_get_file_by_asset_id($asset['id']);
    if (!empty($result->fid)) {
      $file = file_load($result->fid);
    }
  }

  if (empty($file->fid)) {

    media_webdam_api_set_asset_cache($asset);

    $file = new stdClass();
    $file->fid = NULL;
    $file->uri = sprintf('webdam://%d.%s', $asset['id'], $asset['filetype']);
    $file->filename = drupal_basename($asset['name']);
    $file->filemime = file_get_mimetype($file->uri);
    $file->uid = $user->uid;
    $file->status = FILE_STATUS_PERMANENT;
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

  $use_cache = empty($skipCache) && is_numeric($id) && $id != Webdam_Assets_Folder::ROOT_FOLDER_ID;
  if ($use_cache) {
    $cached_item = media_webdam_api_get_asset_cache($id);
  }

  $create_arg = empty($cached_item) ? $id : $cached_item;

  $result = FALSE;

  $client = Webdam_API::getClient('webdam-user-auth');
  $depends = ['client' => $client];

  switch ($type) {
    case 'folder':
      $result = new Webdam_Assets_Folder($create_arg, $depends);
      break;

    case 'asset':
      $result = new Webdam_Assets_Asset($create_arg, $depends);
      break;
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
 * Clears given asset IDs from the local cache.
 *
 * @param array $assetIds
 *   An array of asset IDs.
 */
function media_webdam_api_clear_from_cache(array $assetIds) {
  db_delete('webdam_asset_cache')
    ->condition('asset_id', $assetIds)
    ->execute();
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
