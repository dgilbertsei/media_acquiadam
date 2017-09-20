<?php

/**
 * @file
 * Form related hooks and functions.
 */

/**
 * Implements hook_form_FORM_ID_alter().
 */
function media_acquiadam_browser_form_media_acquiadam_config_settings_alter(&$form, &$form_state) {
  $form['browser'] = [
    '#type' => 'fieldset',
    '#title' => t('Media Browser'),
    '#description' => t('Settings pertinent to the Media browser.'),
    '#collapsible' => TRUE,
    '#tree' => FALSE,
    '#group' => 'additional_settings',
  ];

  $form['browser']['media_acquiadam_browser_per_page'] = [
    '#type' => 'textfield',
    '#title' => t('Results per page'),
    '#description' => t('Controls the number of assets displayed per page.'),
    '#default_value' => variable_get('media_acquiadam_browser_per_page', 25),
    '#element_validate' => ['element_validate_integer_positive'],
    '#size' => 5,
    '#max_size' => 5,
  ];
}

/**
 * Acquia DAM browser form.
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
function media_acquiadam_browser_choose_asset_form(array $form, array &$form_state, array $options = []) {

  // Used for building reset and other URLs to the current page.
  $qp = drupal_get_query_parameters(NULL, ['q', 'page', 'code']);

  $client = AcquiaDAM_API::getClient();
  // 'aa' parameter is used to determine if the user has clicked the
  // authenticate link in our warning message. The state parameter is used by
  // the OAuth2 authentication mechanism and is a way to tell if we're in the
  // process of authenticating.
  $is_authing = !empty($qp['aa']) || !empty($qp['state']);
  $needs_auth = !$client->isAuthenticated() && !$client->hasAuthenticationError();
  if ($needs_auth && $is_authing) {
    $client->authenticate(TRUE);
  }

  $search = !empty($qp['search']) ? $qp['search'] : [];
  unset($qp['search']);
  $form['search'] = [
    '#type' => 'fieldset',
    '#title' => t('Search'),
    '#collapsible' => TRUE,
    '#collapsed' => empty($search),
    '#description' => t('You may search for assets by name, metadata, and other properties. If you have navigated into a folder then the search is limited to that folder and below.'),
    '#access' => $client->isAuthenticated(),
  ];

  $form['search']['keywords'] = [
    '#type' => 'textfield',
    '#title' => t('Keywords'),
    '#description' => t('Refine your search using operations like AND, OR, * and -.'),
    '#default_value' => !empty($search['keywords']) ? $search['keywords'] : '',
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
    '#default_value' => !empty($search['type']) ? $search['type'] : '',
  ];

  $form['search']['sort'] = [
    '#type' => 'select',
    '#title' => t('Sort'),
    '#options' => [
      'filename_asc' => t('File name (A-Z)'),
      'filename_desc' => t('File name (Z-A)'),
      'filesize_asc' => t('File size (Smallest)'),
      'filesize_desc' => t('File size (Largest)'),
      'datecreated_asc' => t('Date created (Oldest)'),
      'datecreated_desc' => t('Date created (Newest)'),
      'datemodified_asc' => t('Date modified (Oldest)'),
      'datemodified_desc' => t('Date modified (Newest)'),
    ],
    '#default_value' => !empty($search['sort']) ? $search['sort'] : 'datecreated_desc',
  ];

  // Gives the user a way to easily reset their search without losing their
  // place.
  $cancel_query = [];
  if (!empty($qp['folderId'])) {
    $cancel_query['folderId'] = $qp['folderId'];
  }
  $form['search']['cancel'] = [
    '#type' => 'link',
    '#title' => t('Reset'),
    '#href' => current_path(),
    '#options' => ['query' => $cancel_query],
    '#access' => !empty($search),
  ];

  $form['search']['search'] = [
    '#type' => 'submit',
    '#value' => t('Search'),
    '#name' => 'search',
  ];

  $form['results'] = [
    '#type' => 'fieldset',
    '#attributes' => ['class' => ['media-browser']],
    '#attached' => [
      'js' => [
        drupal_get_path('module', 'media_acquiadam_browser') . '/js/media_acquiadam_browser.browser.js',
      ],
      'css' => [
        drupal_get_path('module', 'media_acquiadam_browser') . '/css/media_acquiadam_browser.browser.css',
      ],
    ],
  ];

  $form['results']['trail'] = [
    '#theme' => 'media_acquiadam_browser_trail',
    '#title' => t('Trail'),
    '#access' => FALSE,
    '#assets' => [],
  ];

  // There is logic in the after_build and submit handlers that enable the
  // seamless switching between checkboxes and radios.
  $allow_multi = !isset($options['multiselect']);
  $allow_multi |= isset($options['multiselect']) && !empty($options['multiselect']);
  $form['results']['assets'] = [
    '#type' => $allow_multi ? 'checkboxes' : 'radios',
    '#title' => t('Assets'),
    '#options' => [],
    '#assets' => [],
    '#after_build' => ['media_acquiadam_browser_checkboxes_after_build'],
  ];

  $form['results']['empty_message'] = [
    '#markup' => '<p>' . t('There are no available assets.') . '</p>',
    '#access' => empty($form['results']['assets']['#assets']),
  ];

  // Pager is primed in media_acquiadam_browser_choose_asset_form_fill_assets().
  $form['results']['pager'] = ['#theme' => 'pager'];

  try {
    $me = $client->getUser();
    $user = !empty($me['username']) ?
      $me['username'] :
      t('Not authenticated');
  }
  catch (Exception $x) {
    // Not a big deal if this fails.
    $user = t('Error');
  }
  $form['admin'] = [
    '#type' => 'fieldset',
    '#title' => t('Administration options'),
    '#description' => t('Only Acquia DAM administrators can see these options.'),
    '#access' => user_access('administer media acquiadam'),
    'user' => [
      '#type' => 'markup',
      '#markup' => '<p>' . t('Authenticated as %user.', ['%user' => $user]) . '</p>',
    ],
    'clear' => [
      '#type' => 'markup',
      '#markup' => '<p>' . l(t('Clear access token'), 'user/' . $GLOBALS['user']->uid . '/acquiadam/deauth', [
        'query' => ['destination' => current_path()],
      ]) . '</p>',
      '#access' => variable_get('media_acquiadam_client_mode') == 'mixed',
    ],
  ];

  $form['actions'] = [
    '#type' => 'actions',
    'back' => [
      '#type' => 'link',
      '#title' => t('Back'),
      '#href' => '#',
      '#options' => ['external' => TRUE],
      '#attributes' => [
        'rel' => 'no-follow',
        'class' => ['back-link'],
      ],
      '#access' => FALSE,
    ],
    'submit' => [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#access' => $client->isAuthenticated(),
    ],
  ];

  if ($client->isAuthenticated()) {
    media_acquiadam_browser_choose_asset_form_fill_assets($form, $form_state, $options);
  }
  else {
    drupal_set_message(t('The Acquia DAM browser does not have access to your Acquia DAM account. <a href="@auth-url">Please click here to authorize access to Acquia DAM.</a>', [
      '@auth-url' => url(current_path(), ['query' => ['aa' => TRUE]]),
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
function media_acquiadam_browser_choose_asset_form_fill_assets(array &$form, array &$form_state, array $options = []) {

  $qp = drupal_get_query_parameters(NULL, ['q', 'page', 'aa']);

  $folderId = empty($qp['folderId']) ?
    AcquiaDAM_Assets_Folder::ROOT_FOLDER_ID :
    $qp['folderId'];
  $per_page = variable_get('media_acquiadam_browser_per_page', 25);
  $is_root = empty($folderId) || AcquiaDAM_Assets_Folder::ROOT_FOLDER_ID == $folderId;
  $is_search = !empty($qp['search']);

  // We're not under the root folder so show the breadcrumb trail.
  // This allows users to navigate back up a level if they need to.
  if (!$is_root) {
    $trail_assets = media_acquiadam_browser_get_trail($folderId);
    $form['results']['trail']['#access'] = TRUE;
    $form['results']['trail']['#assets'] = $trail_assets;
  }

  $initial_assets = media_acquiadam_browser_get_initial_assets($folderId, $form_state, $per_page);

  // Fake paging the results of the API for the root folder by splitting up
  // its result and overwriting what we originally got.
  if ($is_root && !$is_search && is_array($initial_assets)) {
    $initial_assets = media_acquiadam_browser_fake_paged_root($initial_assets, $per_page);
  }

  if (!empty($initial_assets['assets'])) {
    $result_assets = &$form['results']['assets'];

    // Hide the 'no assets' message.
    $form['results']['empty_message']['#access'] = FALSE;

    foreach ($initial_assets['assets'] as $asset) {
      $result_assets['#options'][$asset['id']] = $asset['name'];
      $result_assets['#assets'][$asset['id']] = $asset;
    }

    // Store the fetched assets so we don't have to do another API lookup on
    // submission. Make sure they are keyed by their asset ID first so we can
    // easily look them up in the submit handler later.
    $keys = array_map(function ($item) {
      return $item['id'];
    }, $result_assets['#assets']);
    $form_state['storage']['assets'] = array_combine($keys, $result_assets['#assets']);
  }

  $form['actions']['back']['#access'] = !$is_root;

  pager_default_initialize($initial_assets['total_count'], $per_page);
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
function media_acquiadam_browser_checkboxes_after_build(array $element, array &$form_state) {

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

    $attributes = [
      'data-asset-expiration' => !empty($asset['expiration']['dateUnix']) ? $asset['expiration']['dateUnix'] : NULL,
      'data-asset-id' => intval($asset['id']),
      'data-asset-status' => $asset['status'],
      'data-asset-type' => $asset['type'],
    ];
    $attributes = array_filter($attributes);

    $element[$asset_id] = [
      // Move the original render array into itself.
      $asset_id => $field,
      'asset' => [
        '#theme' => 'media_acquiadam_browser_asset',
        '#asset' => $asset,
        '#weight' => -1,
      ],
      // Wrap the checkbox/radio in a <li> to mimic Media themeing more closely.
      '#prefix' => sprintf('<li %s>', drupal_attributes($attributes)),
      '#suffix' => '</li>',
      // We have to (re)set a weight because of the opening/closing wrappers.
      '#weight' => ++$weight,
    ];
  }

  $opener_attrs = [
    implode(' ', [
      'media-acquiadam-browser-assets',
      'clearfix',
    ]),
    drupal_html_id('media-acquiadam-browser-library-list'),
  ];

  $element['open-list'] = [
    '#markup' => vsprintf('<div class="%s"><ul id="%s">', $opener_attrs),
    '#weight' => -1,
  ];

  $element['close-list'] = [
    '#markup' => '</ul></div>',
    '#weight' => $weight + 1,
  ];

  return $element;
}

/**
 * Submission handler for media_acquiadam_browser_choose_asset_form.
 *
 * @param array $form
 *   A Drupal form array.
 * @param array $form_state
 *   The current form state.
 */
function media_acquiadam_browser_choose_asset_form_submit(array &$form, array &$form_state) {
  // Searching shortcircuits a lot of the submission logic. We don't need to
  // process selected assets, so create the redirect and exit.
  $is_search = !empty($form_state['triggering_element']) && $form_state['triggering_element']['#name'] == 'search';
  if ($is_search) {
    $qp = drupal_get_query_parameters(NULL, ['q', 'page', 'aa']);
    $qp['search'] = [
      'keywords' => $form_state['values']['keywords'],
      'type' => $form_state['values']['type_filter'],
      'sort' => $form_state['values']['sort'],
    ];
    $form_state['redirect'] = [
      current_path(),
      ['query' => $qp],
    ];
    return;
  }

  $assetIds = media_acquiadam_browser_choose_asset_form_get_selected_assets($form_state);
  if (!empty($assetIds)) {

    $saved_fids = media_acquiadam_browser_choose_asset_form_save_assets($assetIds, $form_state['storage']['assets']);

    $asset_count = count($assetIds);
    $fid_count = count($saved_fids);
    if ($fid_count != $asset_count) {

      drupal_set_message(t('Imported @fid-count files to Drupal and expected @asset-count. Please consult the logs to see what went wrong.', [
        '@asset-count' => $asset_count,
        '@fid-count' => $fid_count,
      ]), 'warning');
    }

    if (!empty($saved_fids)) {
      // If we're not in the Media browser screen then we need a landing page to
      // send users.
      $redirect_uri = url('admin/content/file/acquiadam/saved');
      $query = ['fid' => $saved_fids];

      // This will be set if the user is in a Media browser screen.
      if (!empty($form_state['#media_browser'])) {
        $redirect_uri = url('media/browser/acquiadam');
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
function media_acquiadam_browser_choose_asset_form_save_assets(array $assetIds, array $loadedAssets = []) {

  $saved_fids = [];

  foreach ($assetIds as $assetId) {

    // Load the remote asset if it wasn't already.
    $asset = !empty($loadedAssets[$assetId]) ?
      $loadedAssets[$assetId] :
      media_acquiadam_get_asset($assetId);

    if (empty($asset)) {
      watchdog('media_acquiadam', 'Unable to load asset with ID @id.', ['@id' => $assetId], WATCHDOG_WARNING);
      continue;
    }

    $file = media_acquiadam_create_file_from_asset($asset);
    if (!empty($file->fid)) {
      $saved_fids[] = $file->fid;
    }
    else {
      watchdog('media_acquiadam', 'Unable to create file for asset with ID @id.', ['@id' => $assetId], WATCHDOG_WARNING);
    }
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
function media_acquiadam_browser_choose_asset_form_get_selected_assets(array $form_state) {
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
