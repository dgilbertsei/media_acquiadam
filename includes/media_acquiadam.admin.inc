<?php

/**
 * @file
 * Administration page callbacks for the Media: Acquia DAM module.
 */

/**
 * Displays the media administration page.
 */
function media_acquiadam_config_settings($form, &$form_state) {

  $form['additional_settings'] = [
    '#type' => 'vertical_tabs',
  ];

  $form['api'] = [
    '#type' => 'fieldset',
    '#title' => t('Authentication'),
    '#description' => t('Information used for authenticating with the Acquia DAM API in different scenarios.'),
    '#collapsible' => TRUE,
    '#tree' => FALSE,
    '#group' => 'additional_settings',
  ];

  $form['api']['media_acquiadam_client_id'] = [
    '#type' => 'textfield',
    '#title' => t('Client ID'),
    '#description' => t('Please contact your Customer Success Manager, Implementation Consultant, or file a ticket with the support team for the CLIENT_ID.'),
    '#default_value' => variable_get('media_acquiadam_client_id'),
    '#size' => 50,
    '#required' => TRUE,
  ];

  $form['api']['media_acquiadam_client_secret'] = [
    '#type' => 'textfield',
    '#title' => t('Client Secret'),
    '#description' => t('Please contact your Customer Success Manager, Implementation Consultant, or file a ticket with the support team for the CLIENT_Secret.'),
    '#default_value' => variable_get('media_acquiadam_client_secret'),
    '#size' => 50,
    '#required' => TRUE,
  ];

  $form['api']['media_acquiadam_client_mode'] = [
    '#type' => 'select',
    '#title' => t('Cient mode'),
    '#description' => t('The API client mode to use when making requests. The mixed mode leverages a background API user for specific situations, but will hand off additional authentication to Acquia DAM when necessary. The background only mode will use the background API user for all requests.'),
    '#default_value' => variable_get('media_acquiadam_client_mode', 'mixed'),
    '#options' => [
      'mixed' => t('Mixed (default)'),
      'background' => t('Background only'),
    ],
    '#required' => TRUE,
  ];

  $form['api']['client_warning'] = [
    '#type' => 'container',
    '#states' => [
      'visible' => [
        ':input#edit-media-acquiadam-client-mode' => ['value' => 'mixed'],
      ],
    ],
    'message' => [
      '#markup' => '<p>' . t('In mixed mode it is possible for a user to add an asset that the API user does not have access to. Please ensure the API user has access to both view and download any assets that are expected to be used on the website.') . '</p>',
      '#prefix' => '<div class="messages warning">',
      '#suffix' => '</div>',
    ],
  ];

  $form['api']['background'] = [
    '#type' => 'fieldset',
    '#title' => t('Background user'),
    '#description' => t('The background user is an Acquia DAM user that will be used to provide download links to certain forms of assets, such as documents, to anonymous users. Failure to configure this user will prevent viewing and downloading of these assets.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];

  $user = variable_get('media_acquiadam_background_user');
  $pass = variable_get('media_acquiadam_background_pass');
  $needs_config = empty($user) || empty($pass);
  if ($needs_config) {
    $config_msg = t('Both %user-key and %pass-key must be configured in your settings.php file to enable complete functionality of this module.', [
      '%user-key' => 'media_acquiadam_background_user',
      '%pass-key' => 'media_acquiadam_background_pass',
    ]);

    $code = '<pre><code>$conf[\'media_acquiadam_background_user\'] = \'example.username\';';
    $code .= PHP_EOL;
    $code .= '$conf[\'media_acquiadam_background_pass\'] = \'example.password\';</code></pre>';

    $form['api']['background']['#description'] .= '<p>' . $config_msg . '</p>';
    $form['api']['background']['#description'] .= $code;

    $form['api']['background']['#collapsed'] = FALSE;
    $form['api']['#collapsed'] = FALSE;

    drupal_set_message(t('Background user is missing configuration information.'), 'warning');
  }

  $form['api']['background']['username'] = [
    '#type' => 'item',
    '#title' => t('Username'),
    '#description' => t('The user account being used for background access.'),
    '#markup' => variable_get('media_acquiadam_background_user', t('Not configured!')),
    '#required' => TRUE,
  ];

  $form['api']['background']['password'] = [
    '#type' => 'item',
    '#title' => t('Password'),
    '#description' => t('The user password being used for background access.'),
    '#markup' => variable_get('media_acquiadam_background_pass') ? t('Configured') : t('Not configured!'),
    '#required' => TRUE,
  ];

  $form['cache'] = [
    '#type' => 'fieldset',
    '#title' => t('Caching'),
    '#description' => t('Some asset information is stored locally for performance reasons. This cached information is updated and cleared periodically as necessary. You can force all of this information to be cleared by clicking below. Information will be re-cached the next time the asset is viewed.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#tree' => FALSE,
    '#group' => 'additional_settings',
  ];

  if (!empty($form_state['triggering_element']['#name']) && 'clear' == $form_state['triggering_element']['#name']) {
    drupal_set_message(t('Cleared cached asset information.'), 'status');
    media_acquiadam_flush_cache();
  }

  $count = db_select('acquiadam_asset_cache')
    ->countQuery()
    ->execute()
    ->fetchField();
  $form['cache']['count'] = [
    '#type' => 'item',
    '#markup' => format_plural($count, 'There is 1 cached item.', 'There are @count cached items.'),
  ];

  $form['cache']['media_acquiadam_cache_expiration'] = [
    '#type' => 'textfield',
    '#title' => t('Used lifetime'),
    '#description' => t('Used assets are assets that have been added to Drupal. Set to 0 to disable expiration.'),
    '#default_value' => variable_get('media_acquiadam_cache_expiration', 1440),
    '#element_validate' => ['element_validate_integer'],
    '#field_suffix' => t('minutes'),
    '#maxlength' => 5,
    '#required' => TRUE,
    '#size' => 5,
  ];

  $form['cache']['media_acquiadam_unused_expiration'] = [
    '#type' => 'textfield',
    '#title' => t('Unused lifetime'),
    '#description' => t('Unused assets are assets that are no longer referenced by Drupal. A short timespan is recommended for this value. Set to 0 to disable expiration.'),
    '#default_value' => variable_get('media_acquiadam_unused_expiration', 10),
    '#element_validate' => ['element_validate_integer'],
    '#field_suffix' => t('minutes'),
    '#maxlength' => 5,
    '#required' => TRUE,
    '#size' => 5,
  ];

  $form['cache']['clear'] = [
    '#type' => 'button',
    '#value' => t('Clear'),
    '#name' => 'clear',
  ];

  $form['media'] = [
    '#type' => 'fieldset',
    '#title' => t('Media'),
    '#description' => t('Adjust asset storage and other media behavior.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#tree' => FALSE,
    '#group' => 'additional_settings',
  ];

  $form['media']['media_acquiadam_extension_overrides'] = [
    '#type' => 'textarea',
    '#title' => t('Mimetype adjustment'),
    '#description' => t('This allows you to configure the DAM integration to treat certain file types as others. For example, EPS documents are normally treated as a generic document type by Drupal. Adding "eps png" here will inform the module that EPS files should be treated as PNGs for mimetype purposes. One extension pair per line.'),
    '#default_value' => variable_get('media_acquiadam_extension_overrides', "eps png\n"),
  ];

  return system_settings_form($form);
}
