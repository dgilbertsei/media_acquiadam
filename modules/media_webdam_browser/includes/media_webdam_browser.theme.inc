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

  // A singular asset as displayed on the Media Browser form.
  $ret['media_webdam_browser_asset'] = [
    'variables' => ['asset' => NULL, 'thumbnail' => NULL],
    'template' => 'media-webdam-browser-asset',
    'path' => $path . '/templates',
  ];

  // The breadcrumb trail used to navigate folders.
  $ret['media_webdam_browser_trail'] = [
    'variables' => ['assets' => [], 'title' => t('Trail')],
  ];

  // The menu displayed with each asset in the browser.
  $ret['media_webdam_browser_jump_list'] = [
    'variables' => ['asset' => NULL],
  ];

  // Individual menu items displayed in the browser.
  $ret['media_webdam_browser_jump_list_item'] = [
    'variables' => [
      'icon' => '',
      'label' => NULL,
    ],
  ];

  // The asset information modal content.
  $ret['media_webdam_browser_info_modal'] = [
    'variables' => ['asset' => NULL],
    'template' => 'media-webdam-browser-info-modal',
    'path' => $path . '/templates',
  ];

  return $ret;
}

/**
 * Theme preprocessor for media_webdam_browser_info_modal.
 */
function media_webdam_browser_preprocess_media_webdam_browser_info_modal(&$variables) {

  $asset = $variables['asset'];

  $css_path = drupal_get_path('module', 'media_webdam_browser') . '/css/media_webdam_browser.info_modal.css';
  drupal_add_css($css_path, 'file');

  try {
    $client = Webdam_API::getClient();
    $sub = $client->getSubscription();
  }
  catch (Exception $x) {
    watchdog_exception('media_webdam_browser', $x);
  }

  if (!empty($sub['url']) && user_access('view acquia dam links')) {
    $dam_link = $sub['url'] . '/' . $asset->getDAMPath();
    $variables['dam_link'] = l(t('View this asset in Acquia DAM.'), $dam_link, [
      'absolute' => TRUE,
      'external' => TRUE,
      'attributes' => [
        'rel' => 'external',
        'target' => '_blank',
      ],
    ]);
  }

  // Setup a preview thumbnail if one is available.
  $preview = $asset->getThumbnailUrl(550);
  if (!empty($preview)) {
    $variables['preview'] = theme('image', [
      'path' => $preview,
      'title' => $asset['name'],
      'alt' => $asset['name'],
    ]);
  }

  // Pull out some asset properties for display in the modal.
  $properties = [
    [t('Name'), $asset['name']],
    [t('Description'), $asset['description']],

    [t('File name'), $asset['filename']],
    [t('File size'), format_size($asset['filesize'] * 1024 * 1024)],
    [t('File type'), drupal_strtoupper($asset['filetype'])],

    [t('Color space'), drupal_strtoupper($asset['colorspace'])],
    [t('Width'), $asset['width']],
    [t('Height'), $asset['height']],

    [t('Date captured'), $asset['datecaptured']],
    [t('Date created'), $asset['datecreated']],
    [t('Date modified'), $asset['datemodified']],

    [t('Uploaded by'), $asset['user']['name']],
    [t('Version'), $asset['version']],
    [t('Status'), $asset['status']],
  ];

  // Only show properties that have a value set.
  foreach ($properties as $index => $values) {
    if (empty($values[1])) {
      unset($properties[$index]);
    }
  }

  if ('folder' != $asset['type']) {
    try {
      // Fetch the XMP metadata as this is where the keywords field lives.
      $xmp = $asset->getXMP();
      if (!empty($xmp['active_fields'])) {
        foreach ($xmp['active_fields'] as $field) {
          if (!empty($field['value'])) {
            $properties[] = [$field['field_name'], $field['value']];
          }
        }
      }
    }
    catch (Exception $x) {
      watchdog_exception('media_webdam_browser', $x);
    }
  }

  $variables['properties'] = theme('table', [
    'header' => [t('Name'), t('Value')],
    'rows' => $properties,
    'empty' => t('No information was provided.'),
    'caption' => t('Details'),
    'sticky' => FALSE,
  ]);
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
    $placeholder = variable_get('media_webdam_browser_placeholder', '');
    if (empty($placeholder)) {
      watchdog('media_webdam_browser', 'Browser placeholder image is missing! Please set the media_webdam_browser_placeholder variable to the placeholder image in the files directory!', NULL, WATCHDOG_NOTICE);
    }
    $variables['thumbnail'] = theme('image_style', [
      'style_name' => 'media_thumbnail',
      'path' => $placeholder,
      'title' => $asset['name'],
      'alt' => $asset['name'],
    ]);

    if (module_exists('imagecache_external') && !empty($asset['thumbnailurls'])) {

      $browser_tn_size = variable_get('media_webdam_browser_thumbnail_size', 150);
      $preview = $asset->getThumbnailUrl($browser_tn_size);
      if (!empty($preview)) {
        $variables['thumbnail'] = theme('imagecache_external', [
          'style_name' => 'media_thumbnail',
          'path' => $preview,
          'title' => $asset['name'],
        ]);
      }
    }
  }

  $variables['jump_list'] = theme('media_webdam_browser_jump_list', ['asset' => $asset]);
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
  $breadcrumb = theme('breadcrumb', $var);
  $jump = '';

  if (!empty($variables['assets'])) {
    $last = end($variables['assets']);
    reset($variables['assets']);
    $last = media_webdam_api_get_helper($asset['type'], $asset);
    $jump = theme('media_webdam_browser_jump_list', ['asset' => $last]);
  }

  return '<div class="media-webdam-browser-trail">' . $breadcrumb . $jump . '</div>';
}

/**
 * Theme a single jump list link.
 *
 * @param array $variables
 *   The theme variables.
 *
 * @return string
 *   The rendered jump list item.
 */
function theme_media_webdam_browser_jump_list_item(array $variables) {

  $text = '';
  if (!empty($variables['icon'])) {
    $text .= '<i class="icon">' . ($variables['icon']) . '</i>';
  }

  if (!empty($variables['label'])) {
    $text .= filter_xss($variables['label']);
  }

  return $text;
}

/**
 * Theme implementation for the media browser asset jump list.
 *
 * @param array $variables
 *   The theme variables. Must include an assets array.
 *
 * @return string
 *   The rendered media browser asset jump list.
 */
function theme_media_webdam_browser_jump_list(array $variables) {

  $asset = $variables['asset'];
  $image_base = drupal_get_path('module', 'media_webdam_browser') . '/images/';

  $build = [];

  $css = drupal_get_path('module', 'media_webdam_browser') . '/css/media_webdam_browser.jump_list.css';
  $build['#attached']['css'][] = $css;

  $links = [];
  $links[] = [
    'title' => $asset['name'],
    'attributes' => [
      'class' => ['asset-name'],
    ],
  ];

  try {
    $client = Webdam_API::getClient();
    $sub = $client->getSubscription();
  }
  catch (Exception $x) {
    watchdog_exception('media_webdam_browser', $x);
  }

  if (!empty($sub['url']) && user_access('view acquia dam links')) {
    $open_link = url($sub['url'] . '/' . $asset->getDAMPath(), [
      'absolute' => TRUE,
      'external' => TRUE,
    ]);

    $links[] = [
      'title' => theme('media_webdam_browser_jump_list_item', [
        'icon' => theme('image', ['path' => $image_base . 'ic_launch_black_24dp_1x.png']),
        'label' => t('Open in Acquia DAM'),
      ]),
      'href' => $open_link,
      'attributes' => [
        'target' => '_blank',
        'rel' => 'external',
        'data-action' => 'open',
      ],
      'html' => TRUE,
    ];
  }

  if (module_exists('ctools')) {
    ctools_include('modal');
    ctools_include('ajax');
    ctools_modal_add_js();

    $links[] = [
      'title' => theme('media_webdam_browser_jump_list_item', [
        'icon' => theme('image', ['path' => $image_base . 'ic_info_outline_black_24dp_1x.png']),
        'label' => t('Get info'),
      ]),
      'href' => sprintf('dam/nojs/%s/%d/info', $asset['type'], $asset['id']),
      'attributes' => [
        'data-action' => 'info',
        'class' => ['ctools-use-modal'],
      ],
      'html' => TRUE,
    ];
  }

  $build['jump-list'] = [
    '#theme' => 'ctools_dropdown',
    '#title' => theme('image', ['path' => $image_base . 'ic_arrow_drop_down_black_24dp_1x.png']),
    '#image' => TRUE,
    '#links' => $links,
    '#class' => 'jump-list',
  ];

  return render($build);
}
