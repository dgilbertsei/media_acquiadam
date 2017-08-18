<?php

/**
 * @file
 * Theme hooks and implementations.
 */

/**
 * Implements hook_theme().
 */
function media_webdam_browser_theme($existing, $type, $theme, $path) {

  $ret = [];

  $ret['media_webdam_browser_asset'] = [
    'variables' => ['asset' => NULL, 'thumbnail' => NULL],
    'template' => 'media-webdam-browser-asset',
    'path' => $path . '/templates',
  ];

  $ret['media_webdam_browser_trail'] = [
    'variables' => ['assets' => [], 'title' => t('Trail')],
  ];

  return $ret;
}

/**
 * Theme preprocessor for media_webdam_browser_asset.
 */
function media_webdam_browser_preprocess_media_webdam_browser_asset(&$variables) {

  $asset = $variables['asset'];

  $query = drupal_get_query_parameters(NULL, ['q', 'page', 'folderId', 'aa']);
  $query['folderId'] = $asset['id'];
  // Create a URL for the current page that includes the folder ID so the media
  // browser can filter appropriately.
  $variables['browser_url'] = url(current_path(), ['query' => $query]);

  // If a thumbnail wasn't given we need to define one for display.
  if (empty($variables['thumbnail'])) {

    // Default to a placeholder image in case we can't load one from the asset.
    $missing = drupal_get_path('module', 'media') . '/images/icons/default/image-x-generic.png';
    $variables['thumbnail'] = theme('imagecache_external', [
      'style_name' => 'media_thumbnail',
      'path' => $missing,
      'title' => $asset['name'],
    ]);

    if (module_exists('imagecache_external') && !empty($asset['thumbnailurls'])) {

      $browser_tn_size = variable_get('media_webdam_browser_thumbnail_size', 150);
      foreach ($asset['thumbnailurls'] as $tnurl) {
        if ($browser_tn_size == $tnurl['size'] && !empty($tnurl['url'])) {
          $variables['thumbnail'] = theme('imagecache_external', [
            'style_name' => 'media_thumbnail',
            'path' => $tnurl['url'],
            'title' => $asset['name'],
          ]);
          break;
        }
      }
    }
  }
}

/**
 * Theme implementation for the media browser folder trail.
 *
 * @param array $variables
 *   The theme variables. Must include an assets array.
 *
 * @return string
 *   The rendered media browser trail.
 */
function theme_media_webdam_browser_trail(array $variables) {

  $link_opts = [
    'attributes' => ['data-asset-type' => 'folder'],
    'query' => drupal_get_query_parameters(NULL, ['q', 'page', 'folderId', 'aa']),
  ];

  $links = [];
  if (!empty($variables['assets'])) {

    $links[] = l(t('Root'), current_path(), $link_opts);

    foreach ($variables['assets'] as $asset) {

      if ('folder' == $asset['type']) {
        $link_opts['attributes']['data-asset-id'] = $asset['id'];
        $link_opts['query']['folderId'] = $asset['id'];

        $links[] = l($asset['name'], current_path(), $link_opts);
      }
    }
  }

  $var = ['breadcrumb' => $links];
  return theme('breadcrumb', $var);
}
