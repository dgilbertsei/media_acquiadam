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

/**
 * Clears the current user's authentication token.
 *
 * @return array
 *   A page render array.
 */
function media_acquiadam_deauth_page($account = NULL) {

  try {
    $client = AcquiaDAM_API::getClient('acquiadam-server-auth');
    $client->getOAuth2Client()->clearToken();
    // Nuke the oauth2 session data just to be sure we cleared everything.
    unset($_SESSION['oauth2_client']);
  }
  catch (Exception $x) {
    watchdog_exception('media_acquiadam', $x);
    drupal_set_message(t('There was a problem clearing the token.'), 'error');
  }

  $success_message = t('Your OAuth2 DAM access token has been cleared.');

  // If a destination argument was set we should send the user back to where
  // they came from.
  if (!empty($_GET['destination'])) {
    drupal_set_message($success_message, 'status');
    drupal_goto($_GET['destination']);
  }

  $build = [];

  // Build a simple status page if someone goes directly to the page.
  $build['message'] = [
    '#type' => 'markup',
    '#markup' => '<p>' . $success_message . '</p>',
  ];

  return $build;
}
