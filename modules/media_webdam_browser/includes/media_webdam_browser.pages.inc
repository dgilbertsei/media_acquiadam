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
function media_webdam_browser_choose_asset_saved_landing_page($selected = NULL) {

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
    '#href' => url('admin/content/file/webdam'),
  ];

  $qp = drupal_get_query_parameters();
  if (!empty($qp['fid'])) {
    $selected = file_load_multiple($qp['fid']);
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
function media_webdam_browser_deauth_page($account = NULL) {

  try {
    $client = Webdam_API::getClient('webdam-server-auth');
    $client->getOAuth2Client()->clearToken();
    unset($_SESSION['oauth2_client']);
  }
  catch (Exception $x) {
    watchdog_exception('media_webdam_browser', $x);
    drupal_set_message(t('There was a problem clearing the token.'), 'error');
  }

  $success_message = t('Your OAuth2 DAM access token has been cleared.');

  if (!empty($_GET['destination'])) {
    drupal_set_message($success_message, 'status');
    drupal_goto($_GET['destination']);
  }

  $build = [];

  $build['message'] = [
    '#type' => 'markup',
    '#markup' => '<p>' . $success_message . '</p>',
  ];

  return $build;
}

/**
 * Callback for the asset information modal.
 *
 * @param bool $js
 *   TRUE if the page was loaded via AJAX.
 * @param int $assetId
 *   The asset ID that is being requested.
 *
 * @return array|int
 *   A page render array or a menu response code.
 */
function media_webdam_browser_info_modal_page($js, $assetId) {
  if (empty($assetId)) {
    return MENU_NOT_FOUND;
  }

  $asset = media_webdam_api_get_asset($assetId);
  if (empty($asset)) {
    return MENU_NOT_FOUND;
  }

  $build = [];

  $build['preview'] = [
    '#theme' => 'media_webdam_browser_info_modal',
    '#asset' => $asset,
  ];

  if ($js && module_exists('ctools')) {
    ctools_include('modal');
    ctools_include('ajax');
    return ctools_modal_render($asset['name'], render($build));
  }

  return $build;
}
