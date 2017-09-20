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
      $asset = media_acquiadam_file_to_asset($file);
      $preview = [
        '#theme' => 'media_acquiadam_browser_asset',
        '#asset' => $asset,
      ];
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
