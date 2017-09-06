<?php

/**
 * @file
 * Page callback implementations.
 */

/**
 * Landing page for when someone selects assets with the browser form.
 *
 * @return array
 *   A render array.
 */
function media_acquiadam_browser_choose_asset_saved_landing_page($selected = NULL) {

  $build = [];

  $build['selections'] = [
    '#theme' => 'table',
    '#header' => [
      t('Preview'),
      t('Name'),
      t('Type'),
    ],
    '#empty' => t('There were no selected files.'),
    '#rows' => [],
  ];

  $build['back'] = [
    '#type' => 'link',
    '#title' => t('Back to the browser'),
    '#href' => url('admin/content/file/acquiadam'),
  ];

  // 'fid' will contain a list of files that were saved by the user.
  $qp = drupal_get_query_parameters();
  if (!empty($qp['fid'])) {
    $selected = file_load_multiple($qp['fid']);
    // Fill out the selections table with this info.
    foreach ($selected as $file) {
      $uri = entity_uri('file', $file);
      $preview = media_get_thumbnail_preview($file);
      $build['selections']['#rows'][] = [
        render($preview),
        l($file->filename, $uri['path'], $uri['options']),
        drupal_ucfirst($file->type),
      ];
    }
  }

  return $build;
}

/**
 * Clears the current user's authentication token.
 *
 * @return array
 *   A page render array.
 */
function media_acquiadam_browser_deauth_page($account = NULL) {

  try {
    $client = AcquiaDAM_API::getClient('acquiadam-server-auth');
    $client->getOAuth2Client()->clearToken();
    // Nuke the oauth2 session data just to be sure we cleared everything.
    unset($_SESSION['oauth2_client']);
  }
  catch (Exception $x) {
    watchdog_exception('media_acquiadam_browser', $x);
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

/**
 * Callback for the asset information modal.
 *
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The asset that is being requested.
 * @param bool $js
 *   TRUE if the page was loaded via AJAX.
 *
 * @return array|int
 *   A page render array or a menu response code.
 */
function media_acquiadam_browser_info_modal_page(AcquiaDAM_Assets_AbstractAsset $asset, $js) {

  if (empty($asset)) {
    return MENU_NOT_FOUND;
  }

  $build = [];

  $build['preview'] = [
    '#theme' => 'media_acquiadam_browser_info_modal',
    '#asset' => $asset,
  ];

  if ($js && module_exists('ctools')) {
    ctools_include('modal');
    ctools_include('ajax');
    return ctools_modal_render($asset['name'], render($build));
  }

  return $build;
}
