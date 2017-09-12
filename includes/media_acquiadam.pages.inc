<?php

/**
 * @file
 * Page callback implementations.
 */

/**
 * Menu callback for redirecting to asset download links.
 *
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The asset.
 *
 * @return int
 *   A menu response code.
 */
function media_acquiadam_asset_download_redirect(AcquiaDAM_Assets_AbstractAsset $asset) {
  try {
    $url = $asset->getDownloadUrl();
    if (!empty($url)) {
      drupal_goto($url, ['external' => TRUE], 307);
    }
  }
  catch (Exception $x) {
    watchdog_exception('media_acquiadam', $x);
    drupal_set_message(t('Unable to get a download URL for @name.', ['@name' => $asset['name']]), 'error');
    return MENU_ACCESS_DENIED;
  }

  return MENU_NOT_FOUND;
}

/**
 * Refresh the Acquia DAM asset from the DAM source.
 *
 * @param object $file
 *   The file that has an associated asset.
 *
 * @return int
 *   A menu response code or a redirect.
 */
function media_acquiadam_dam_refresh($file) {
  $asset = media_acquiadam_file_to_asset($file);
  if (empty($asset)) {
    return MENU_NOT_FOUND;
  }

  media_acquiadam_api_flush_item_cache([$asset['id']]);

  drupal_set_message(t('Local cache was cleared for @name (@id).', [
    '@name' => $asset['name'],
    '@id' => $asset['id'],
  ]), 'status');

  $uri = entity_uri('file', $file);
  drupal_goto($uri['path']);
}

/**
 * View the Acquia DAM asset on the DAM source.
 *
 * @param object $file
 *   The file that has an associated asset.
 *
 * @return int
 *   A menu response code or a redirect.
 */
function media_acquiadam_dam_view($file) {
  $asset = media_acquiadam_file_to_asset($file);
  if (empty($asset)) {
    return MENU_NOT_FOUND;
  }

  try {
    drupal_goto($asset->getDAMUrl(), [
      'absolute' => TRUE,
      'external' => TRUE,
    ]);
  }
  catch (Exception $x) {
    watchdog_exception('media_acquiadam', $x);
  }

  return MENU_ACCESS_DENIED;
}
