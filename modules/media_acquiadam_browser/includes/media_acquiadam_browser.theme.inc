<?php

/**
 * @file
 * Theme hooks and implementations.
 */

/**
 * Implements hook_theme().
 */
function media_acquiadam_browser_theme($existing, $type, $theme, $path) {

  $ret = [];

  // A singular asset as displayed on the Media Browser form.
  $ret['media_acquiadam_browser_asset'] = [
    'variables' => ['asset' => NULL, 'thumbnail' => NULL],
    'template' => 'media-acquiadam-browser-asset',
    'path' => $path . '/templates',
  ];

  // The breadcrumb trail used to navigate folders.
  $ret['media_acquiadam_browser_trail'] = [
    'variables' => ['assets' => [], 'title' => t('Trail')],
  ];

  // The menu displayed with each asset in the browser.
  $ret['media_acquiadam_browser_jump_list'] = [
    'variables' => ['asset' => NULL],
  ];

  // Individual menu items displayed in the browser.
  $ret['media_acquiadam_browser_jump_list_item'] = [
    'variables' => [
      'icon' => '',
      'label' => NULL,
    ],
  ];

  // The asset information modal content.
  $ret['media_acquiadam_browser_info_modal'] = [
    'variables' => ['asset' => NULL],
    'template' => 'media-acquiadam-browser-info-modal',
    'path' => $path . '/templates',
  ];

  return $ret;
}

/**
 * Theme preprocessor for media_acquiadam_browser_info_modal.
 */
function media_acquiadam_browser_preprocess_media_acquiadam_browser_info_modal(&$variables) {

  $asset = $variables['asset'];

  $css_path = drupal_get_path('module', 'media_acquiadam_browser') . '/css/media_acquiadam_browser.info_modal.css';
  drupal_add_css($css_path, 'file');

  if (user_access('view acquiadam links')) {
    $variables['dam_link'] = l(t('View this asset in AcquiaDAM.'), $asset->getDAMUrl(), [
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
    [t('Name'), !empty($asset['name']) ? $asset['name'] : NULL],
    [t('Description'), !empty($asset['description']) ? $asset['description'] : NULL],

    [t('Expires'), !empty($asset['expiration']['date']) ? $asset['expiration']['date'] : NULL],

    [t('File name'), !empty($asset['filename']) ? $asset['filename'] : NULL],
    [t('File size'), !empty($asset['filesize']) ? format_size($asset['filesize'] * 1024 * 1024) : NULL],
    [t('File type'), !empty($asset['filetype']) ? drupal_strtoupper($asset['filetype']) : NULL],

    [t('Color space'), !empty($asset['colorspace']) ? drupal_strtoupper($asset['colorspace']) : NULL],
    [t('Width'), !empty($asset['width']) ? $asset['width'] : NULL],
    [t('Height'), !empty($asset['height']) ? $asset['height'] : NULL],

    [t('Date captured'), !empty($asset['datecaptured']) ? $asset['datecaptured'] : NULL],
    [t('Date created'), !empty($asset['datecreated']) ? $asset['datecreated'] : NULL],
    [t('Date modified'), !empty($asset['datemodified']) ? $asset['datemodified'] : NULL],

    [t('Uploaded by'), !empty($asset['user']['name']) ? $asset['user']['name'] : NULL],
    [t('Version'), !empty($asset['version']) ? $asset['version'] : NULL],
    [t('Status'), !empty($asset['status']) ? drupal_ucfirst($asset['status']) : NULL],
  ];

  // Only show properties that have a value set.
  foreach ($properties as $index => $values) {
    if (empty($values[1])) {
      unset($properties[$index]);
    }
  }

  if ('folder' != $asset['type']) {

    $metaprops = [];
    try {
      // Fetch the XMP metadata as this is where the keywords field lives.
      $xmp = $asset->getXMP();
      if (!empty($xmp['active_fields'])) {
        foreach ($xmp['active_fields'] as $field) {
          if (!empty($field['value'])) {
            $metaprops[] = [$field['field_name'], $field['value']];
          }
        }
      }
    }
    catch (Exception $x) {
      watchdog_exception('media_acquiadam_browser', $x);
    }

    if (!empty($metaprops)) {
      $properties[] = [
        [
          'data' => t('Metadata'),
          'header' => TRUE,
          'colspan' => 2,
        ],
      ];
      $properties = array_merge($properties, $metaprops);
    }
  }

  $variables['properties'] = theme('table', [
    'header' => [t('Name'), t('Value')],
    'rows' => $properties,
    'empty' => t('No information was provided.'),
    'caption' => t('Asset information'),
    'sticky' => FALSE,
  ]);
}

/**
 * Theme preprocessor for media_acquiadam_browser_asset.
 */
function media_acquiadam_browser_preprocess_media_acquiadam_browser_asset(&$variables) {

  $asset = $variables['asset'];

  $query = drupal_get_query_parameters(NULL, ['q', 'page', 'folderId', 'aa']);
  $query['folderId'] = $asset['id'];
  // Create a URL for the current page that includes the folder ID so the media
  // browser can filter appropriately.
  $variables['browser_url'] = url(current_path(), ['query' => $query]);

  // If a thumbnail wasn't given we need to define one for display.
  if (empty($variables['thumbnail'])) {

    // Default to a placeholder image in case we can't load one from the asset.
    $placeholder = variable_get('media_acquiadam_browser_placeholder', '');
    if (empty($placeholder)) {
      watchdog('media_acquiadam_browser', 'Browser placeholder image is missing! Please set the media_acquiadam_browser_placeholder variable to the placeholder image in the files directory!', NULL, WATCHDOG_NOTICE);
    }
    $variables['thumbnail'] = theme('image_style', [
      'style_name' => 'media_thumbnail',
      'path' => $placeholder,
      'title' => $asset['name'],
      'alt' => $asset['name'],
    ]);

    if (!empty($asset['thumbnailurls'])) {
      $browser_tn_size = variable_get('media_acquiadam_browser_thumbnail_size', 150);
      $preview = $asset->getThumbnailUrl($browser_tn_size);
      if (!empty($preview)) {
        $variables['thumbnail'] = theme('image_style', [
          'style_name' => 'media_thumbnail',
          'path' => media_acquiadam_image_style_url($asset),
          'title' => $asset['name'],
          'alt' => $asset['name'],
        ]);
      }
    }
  }

  $variables['jump_list'] = theme('media_acquiadam_browser_jump_list', ['asset' => $asset]);

  $variables['attributes_array'] = [
    'data-asset-expiration' => !empty($asset['expiration']['dateUnix']) ? $asset['expiration']['dateUnix'] : NULL,
    'data-asset-id' => intval($asset['id']),
    'data-asset-status' => $asset['status'],
    'data-asset-type' => $asset['type'],
  ];
  $variables['attributes_array'] = array_filter($variables['attributes_array']);

  $variables['attributes'] = drupal_attributes($variables['attributes_array']);
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
function theme_media_acquiadam_browser_trail(array $variables) {

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
    $last = media_acquiadam_api_get_helper($asset['type'], $asset);
    $jump = theme('media_acquiadam_browser_jump_list', ['asset' => $last]);
  }

  return '<div class="media-acquiadam-browser-trail">' . $breadcrumb . $jump . '</div>';
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
function theme_media_acquiadam_browser_jump_list_item(array $variables) {

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
function theme_media_acquiadam_browser_jump_list(array $variables) {

  $asset = $variables['asset'];
  $image_base = drupal_get_path('module', 'media_acquiadam_browser') . '/images/';

  $build = [];

  $css = drupal_get_path('module', 'media_acquiadam_browser') . '/css/media_acquiadam_browser.jump_list.css';
  $build['#attached']['css'][] = $css;

  $links = [];
  $links[] = [
    'title' => $asset['name'],
    'attributes' => [
      'class' => ['asset-name'],
    ],
  ];

  if (user_access('view acquiadam links')) {
    $links[] = [
      'title' => theme('media_acquiadam_browser_jump_list_item', [
        'icon' => theme('image', ['path' => $image_base . 'ic_launch_black_24dp_1x.png']),
        'label' => t('Open in AcquiaDAM'),
      ]),
      'href' => $asset->getDAMUrl(),
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
      'title' => theme('media_acquiadam_browser_jump_list_item', [
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
