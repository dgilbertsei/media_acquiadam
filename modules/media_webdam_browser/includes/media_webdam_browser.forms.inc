<?php

/**
 * @file
 * Form related hooks and functions.
 */

/**
 * Implements hook_form_FORM_ID_alter().
 */
function media_webdam_browser_form_media_webdam_config_settings_alter(&$form, &$form_state) {
  $form['browser'] = [
    '#type' => 'fieldset',
    '#title' => t('Media Browser'),
    '#description' => t('Settings pertinent to the Media browser.'),
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
    '#tree' => FALSE,
  ];

  $form['browser']['media_webdam_browser_per_page'] = [
    '#type' => 'textfield',
    '#title' => t('Results per page'),
    '#description' => t('Controls the number of assets displayed per page.'),
    '#default_value' => variable_get('media_webdam_browser_per_page', 25),
    '#element_validate' => ['element_validate_integer_positive'],
    '#size' => 5,
  ];

  $form['browser']['media_webdam_browser_thumbnail_size'] = [
    '#type' => 'select',
    '#title' => t('Thumbnail source size'),
    '#description' => t('The source remote thumbnail size to use when creating browser thumbnails locally.'),
    '#default_value' => 150,
    '#options' => [
      100 => 100,
      150 => 150,
      220 => 220,
      310 => 310,
      550 => t('550 (Medium)'),
      1280 => 1280,
    ],
  ];
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function media_webdam_browser_form_file_entity_add_upload_alter(&$form, &$form_state) {
  $step = $form['#step'];
  $options = $form['#options'];

  array_unshift($form['#submit'], 'media_webdam_browser_add_upload_submit');
}

/**
 * Submit handler for the add file form.
 */
function media_webdam_browser_add_upload_submit(array $form, array &$form_state) {
  $step = $form['#step'];
  $options = $form['#options'];

  if (!empty($form_state['storage']['upload'])) {
    $file = file_load($form_state['storage']['upload']);
    if ($file) {

    }
  }
}

/**
 * Webdam browser form.
 *
 * @param array $form
 *   A form render array.
 * @param array $form_state
 *   The current form state.
 * @param array $options
 *   An array of options as passed from the Media Browser plugin.
 *
 * @return array
 *   A form render array.
 */
function media_webdam_browser_choose_asset_form(array $form, array &$form_state, array $options = []) {

  // Used for building reset and other URLs to the current page.
  $qp = drupal_get_query_parameters(NULL, ['q', 'page']);

  $client = oauth2_client_load('webdam-server-auth');
  $is_authed = $client->getAccessToken(!empty($qp['allow_auth']));
  unset($qp['allow_auth']);

  $is_search = !empty($form_state['values']['search']) && ($form_state['values']['op'] == $form_state['values']['search']);
  $form['search'] = [
    '#type' => 'fieldset',
    '#title' => t('Search'),
    '#collapsible' => TRUE,
    '#collapsed' => !$is_search,
    '#description' => t('You may search for assets by name, metadata, and other properties. If you have navigated into a folder then the search is limited to that folder and below.'),
    '#access' => !empty($is_authed),
  ];

  $form['search']['keywords'] = [
    '#type' => 'textfield',
    '#title' => t('Keywords'),
  ];

  $form['search']['type_filter'] = [
    '#type' => 'select',
    '#title' => t('Type'),
    '#empty_option' => t('Any'),
    '#empty_value' => '',
    '#options' => [
      'image' => t('Image'),
      'audiovideo' => t('Audio/Video'),
      'document' => t('Documents'),
      'presentation' => t('Presentations'),
      'other' => t('Other'),
    ],
  ];

  // Gives the user a way to easily reset their search without losing their
  // place.
  $form['search']['cancel'] = [
    '#type' => 'link',
    '#title' => t('Reset'),
    '#href' => current_path(),
    '#options' => ['query' => $qp],
    '#access' => $is_search,
  ];

  $form['search']['search'] = [
    '#type' => 'submit',
    '#value' => t('Search'),
  ];

  $form['results'] = [
    '#type' => 'fieldset',
    '#attributes' => ['class' => ['media-browser']],
    '#attached' => [
      'js' => [
        drupal_get_path('module', 'media_webdam_browser') . '/js/media_webdam_browser.browser.js',
      ],
      'css' => [
        drupal_get_path('module', 'media') . '/css/media.css',
        drupal_get_path('module', 'media_webdam_browser') . '/css/media_webdam_browser.browser.css',
      ],
    ],
  ];

  $form['results']['trail'] = [
    '#theme' => 'media_webdam_browser_trail',
    '#title' => t('Trail'),
    '#access' => FALSE,
    '#assets' => [],
  ];

  // There is logic in the after_build and submit handlers that enable the
  // seamless switching between checkboxes and radios.
  $form['results']['assets'] = [
    '#type' => !empty($options['multiselect']) ? 'checkboxes' : 'radios',
    '#title' => t('Assets'),
    '#options' => [],
    '#assets' => [],
    '#after_build' => ['media_webdam_browser_checkboxes_after_build'],
  ];

  $form['results']['empty_message'] = [
    '#markup' => '<p>' . t('There are no available assets.') . '</p>',
    '#access' => empty($form['results']['assets']['#assets']),
  ];

  $form['results']['pager'] = ['#theme' => 'pager'];

  $form['actions'] = [
    '#type' => 'actions',
    'submit' => [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#access' => !empty($is_authed),
    ],
  ];

  if (!empty($is_authed)) {
    media_webdam_browser_choose_asset_form_fill_assets($form, $form_state, $options);
  }
  else {
    $auth_query = ['allow_auth' => TRUE] + $qp;
    drupal_set_message(t('The Webdam browser does not have access to your Webdam account. <a href="@auth-url">Please click here to authorize access to Webdam.</a>', [
      '@auth-url' => url(current_path(), ['query' => $auth_query]),
    ]), 'warning');
  }

  return $form;
}

/**
 * Helper function to adjust the asset chooser form values.
 *
 * @param array $form
 *   A form render array.
 * @param array $form_state
 *   The current form state.
 * @param array $options
 *   An array of options as passed from the Media Browser plugin.
 */
function media_webdam_browser_choose_asset_form_fill_assets(array &$form, array &$form_state, array $options = []) {

  $qp = drupal_get_query_parameters(NULL, ['q', 'page']);

  $folderId = empty($qp['folderId']) ?
    Webdam_Assets_Folder::ROOT_FOLDER_ID :
    $qp['folderId'];
  $per_page = variable_get('media_webdam_browser_per_page', 25);
  $is_root = empty($folderId) || Webdam_Assets_Folder::ROOT_FOLDER_ID == $folderId;

  // We're not under the root folder so show the breadcrumb trail.
  // This allows users to navigate back up a level if they need to.
  if (!$is_root) {
    $trail_assets = media_webdam_browser_get_trail($folderId);
    $form['results']['trail']['#access'] = TRUE;
    $form['results']['trail']['#assets'] = $trail_assets;
  }

  $initial_assets = media_webdam_browser_get_initial_assets($folderId, $form_state, $per_page);
  // Initial assets and final assets are split because the root folder listing
  // doesn't support paging, so we need some special logic to handle faking it.
  $final_assets = [];

  if ($is_root) {
    // Fake paging the results of the API for the root folder by splitting up
    // its result and overwriting what we originally got.
    $chunked = array_chunk($initial_assets['assets'], $per_page);
    $current = pager_find_page();
    if ($current >= count($chunked)) {
      $current = max(0, count($chunked) - 1);
    }
    $initial_assets['total_count'] = count($initial_assets['assets']);
    $initial_assets['assets'] = empty($chunked[$current]) ?
      [] :
      $chunked[$current];
  }

  $pager_total = $initial_assets['total_count'];
  $final_assets = $initial_assets['assets'];
  pager_default_initialize($pager_total, $per_page);

  if (!empty($final_assets)) {
    $result_assets = &$form['results']['assets'];

    // Hide the 'no assets' message.
    $form['results']['empty_message']['#access'] = FALSE;

    foreach ($final_assets as $asset) {
      $result_assets['#options'][$asset['id']] = $asset['name'];
      $result_assets['#assets'][$asset['id']] = $asset->toArray();
    }

    // Store the fetched assets so we don't have to do another API lookup on
    // submission. Make sure they are keyed by their asset ID first so we can
    // easily look them up in the submit handler later.
    $keys = array_map(function ($item) {
      return $item['id'];
    }, $result_assets['#assets']);
    $form_state['storage']['assets'] = array_combine($keys, $result_assets['#assets']);
  }
}

/**
 * Render checkboxes and radios as a list with asset thumbnails.
 *
 * @param array $element
 *   The form element render array.
 * @param array $form_state
 *   The current form state.
 *
 * @return array
 *   The modified form element render array.
 */
function media_webdam_browser_checkboxes_after_build(array $element, array &$form_state) {

  // We need to adjust the element weight so that elements remain in the proper
  // order once we add our opening/closing wrapper.
  $weight = 0;

  foreach (element_children($element) as $asset_id) {

    $asset = $element['#assets'][$asset_id];

    $field = $element[$asset_id];

    // We don't want to show a checkbox for folder or inactive assets as it d
    // oesn't make sense to select one in that scenario.
    if ('folder' == $asset['type'] || 'inactive' == $asset['status']) {
      $field['#access'] = FALSE;
    }

    // Go ahead and hide the checkbox label because it gets displayed as part of
    // our asset theme.
    $field['#title_display'] = 'invisible';

    $element[$asset_id] = [
      // Move the original render array into itself.
      $asset_id => $field,
      'asset' => [
        '#theme' => 'media_webdam_browser_asset',
        '#asset' => $asset,
        '#weight' => -1,
      ],
      // Wrap the checkbox/radio in a <li> to mimic Media themeing more closely.
      '#prefix' => sprintf('<li data-asset-type="%s" data-asset-id="%d">', $asset['type'], $asset['id']),
      '#suffix' => '</li>',
      // We have to (re)set a weight because of the opening/closing wrappers.
      '#weight' => ++$weight,
    ];
  }

  $opener_attrs = [
    implode(' ', [
      'media-webdam-browser-assets',
      'media-display-thumbnails',
      'media-clear',
      'clearfix',
    ]),
    drupal_html_id('media-browser-library-list'),
    implode(' ', [
      'media-list-thumbnails',
    ]),
  ];

  $element['open-list'] = [
    '#markup' => vsprintf('<div class="%s"><ul id="%s" class="%s">', $opener_attrs),
    '#weight' => -1,
  ];

  $element['close-list'] = [
    '#markup' => '</ul></div>',
    '#weight' => $weight + 1,
  ];

  return $element;
}

/**
 * Submission handler for media_webdam_browser_choose_asset_form.
 *
 * @param array $form
 *   A Drupal form array.
 * @param array $form_state
 *   The current form state.
 */
function media_webdam_browser_choose_asset_form_submit(array &$form, array &$form_state) {
  // When we are searching we don't need to do any other processing in the
  // submit handler. Just rebuild the form and leave.
  if ($form_state['values']['op'] == $form_state['values']['search']) {
    $form_state['rebuild'] = TRUE;
    return;
  }

  $assetIds = media_webdam_browser_choose_asset_form_get_selected_assets($form_state);
  if (!empty($assetIds)) {

    $saved_fids = media_webdam_browser_choose_asset_form_save_assets($assetIds, $form_state['storage']['assets']);

    $asset_count = count($assetIds);
    $fid_count = count($saved_fids);
    if ($fid_count != $asset_count) {

      drupal_set_message(t('Imported @fid-count files to Drupal and expected @asset-count. Please consult the logs to see what went wrong.', [
        '@asset-count' => $asset_count,
        '@fid-count' => $fid_count,
      ]), 'warning');
    }

    if (!empty($saved_fids)) {
      $redirect_uri = url('admin/content/file/webdam/saved');
      $query = ['fid' => $saved_fids];

      if (!empty($form_state['#media_browser'])) {
        $redirect_uri = url('media/browser/webdam');
      }

      $form_state['redirect'] = [
        $redirect_uri,
        ['query' => $query],
      ];
    }
  }
}

/**
 * Create Drupal file entities given an array of asset IDs.
 *
 * @param array $assetIds
 *   An array of asset IDs to create Drupal entities for.
 * @param array $loadedAssets
 *   An array of already loaded assets, keyed by their asset ID.
 *
 * @return int[]
 *   An array of file IDs that were created.
 */
function media_webdam_browser_choose_asset_form_save_assets(array $assetIds, array $loadedAssets) {

  $saved_fids = [];

  try {
    foreach ($assetIds as $assetId) {

      // Load the remote asset if it wasn't already.
      $asset = !empty($loadedAssets[$assetId]) ?
        media_webdam_api_get_asset($loadedAssets[$assetId]) :
        media_webdam_api_get_asset($assetId);

      if (empty($asset)) {
        watchdog('media_webdam', 'Unable to load asset with ID @id.', ['@id' => $assetId], WATCHDOG_WARNING);
        continue;
      }

      $file = media_webdam_api_create_webdam_file($asset);
      if (!empty($file->fid)) {
        $saved_fids[] = $file->fid;
      }
      else {
        watchdog('media_webdam', 'Unable to create file for asset with ID @id.', ['@id' => $assetId], WATCHDOG_WARNING);
      }
    }
  }
  catch (Exception $x) {
    drupal_set_message($x->getMessage(), 'error');
    watchdog_exception('media_webdam', $x);
  }

  return $saved_fids;
}

/**
 * Gets the user-selected assets from the media browser submission.
 *
 * @param array $form_state
 *   A form state array.
 *
 * @return int[]
 *   An array of asset IDs.
 */
function media_webdam_browser_choose_asset_form_get_selected_assets(array $form_state) {
  $assetIds = !empty($form_state['values']['assets']) ?
    $form_state['values']['assets'] :
    [];

  // When the form field is a 'radios' type assets will be a string, so convert
  // it to an array so we can use the same logic for it and checkboxes.
  if (!empty($assetIds) && is_string($assetIds)) {
    $assetIds = [$assetIds => $assetIds];
  }

  $assetIds = array_map('intval', $assetIds);
  // Remove options that weren't selected or weren't properly converted.
  $assetIds = array_filter($assetIds);

  return $assetIds;
}

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
