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
