<?php

/**
 * @file
 * Administration page callbacks for the Media: Webdam module.
 */

/**
 * Displays the media administration page.
 */
function media_webdam_config_settings($form, &$form_state) {

  $form['api'] = [
    '#type' => 'fieldset',
    '#title' => t('Authentication'),
    '#description' => t('Information used for authenticating with the Webdam API in different scenarios.'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#tree' => FALSE,
  ];

  $form['api']['media_webdam_client_id'] = [
    '#type' => 'textfield',
    '#title' => t('Client ID'),
    '#description' => t('Please contact your Customer Success Manager, Implementation Consultant, or file a ticket with the <a href="https://www.damsuccess.com/hc/en-us/requests/new">support team</a> for the CLIENT_ID.'),
    '#default_value' => variable_get('media_webdam_client_id', ''),
    '#required' => TRUE,
  ];

  $form['api']['media_webdam_client_secret'] = [
    '#type' => 'textfield',
    '#title' => t('Client Secret'),
    '#description' => t('Please contact your Customer Success Manager, Implementation Consultant, or file a ticket with the <a href="https://www.damsuccess.com/hc/en-us/requests/new">support team</a> for the CLIENT_Secret.'),
    '#default_value' => variable_get('media_webdam_client_secret', ''),
    '#required' => TRUE,
  ];

  $form['api']['media_webdam_client_mode'] = [
    '#type' => 'select',
    '#title' => t('Cient mode'),
    '#description' => t('The API client mode to use when making requests. The mixed mode leverages a background API user for specific situations, but will hand off additional authentication to Webdam when necessary. The background only mode will use the background API user for all requests.'),
    '#default_value' => variable_get('media_webdam_client_mode', 'mixed'),
    '#options' => [
      'mixed' => t('Mixed (default)'),
      'background' => t('Background only'),
    ],
    '#required' => TRUE,
  ];

  $form['api']['background'] = [
    '#type' => 'fieldset',
    '#title' => t('Background user'),
    '#description' => t('The background user is a Webdam user that will be used to provide download links to certain forms of assets, such as documents, to anonymous users. Failure to configure this user will prevent viewing and downloading of these assets.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];

  $user = variable_get('media_webdam_background_user');
  $pass = variable_get('media_webdam_background_pass');
  $needs_config = empty($user) || empty($pass);
  if ($needs_config) {
    $config_msg = t('Both %user-key and %pass-key must be configured in your settings.php file to enable complete functionality of this module.', [
      '%user-key' => 'media_webdam_background_user',
      '%pass-key' => 'media_webdam_background_pass',
    ]);

    $code = '<pre><code>$conf[\'media_webdam_background_user\'] = \'example.username\';';
    $code .= PHP_EOL;
    $code .= '$conf[\'media_webdam_background_pass\'] = \'example.password\';</code></pre>';

    $form['api']['background']['#description'] .= '<p>' . $config_msg . '</p>';
    $form['api']['background']['#description'] .= $code;

    $form['api']['background']['#collapsed'] = FALSE;

    drupal_set_message(t('Background user is missing configuration information.'), 'warning');
  }

  $form['api']['background']['username'] = [
    '#type' => 'item',
    '#title' => t('Username'),
    '#description' => t('The user account being used for background access.'),
    '#markup' => variable_get('media_webdam_background_user', t('Not configured!')),
    '#required' => TRUE,
  ];

  $form['api']['background']['password'] = [
    '#type' => 'item',
    '#title' => t('Password'),
    '#description' => t('The user password being used for background access.'),
    '#markup' => variable_get('media_webdam_background_pass') ? t('Configured') : t('Not configured!'),
    '#required' => TRUE,
  ];

  $form['cache'] = [
    '#type' => 'fieldset',
    '#title' => t('Caching'),
    '#description' => t('Some asset information is stored locally for performance reasons. This cached information is updated and cleared periodically as necessary. You can force all of this information to be cleared by clicking below. Information will be recached the next time the asset is viewed.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  ];


  if (!empty($form_state['triggering_element']['#name']) && 'clear' == $form_state['triggering_element']['#name']) {
    drupal_set_message(t('Cleared cached asset information.'), 'status');
    db_delete('webdam_asset_cache')->execute();

    if (module_exists('imagecache_external')) {
      imagecache_external_flush_cache();
    }
  }

  $count = db_select('webdam_asset_cache')
    ->countQuery()
    ->execute()
    ->fetchField();
  $form['cache']['count'] = [
    '#type' => 'item',
    '#markup' => format_plural($count, 'There is 1 cached item.', 'There are @count cached items.'),
  ];

  $form['cache']['clear'] = [
    '#type' => 'button',
    '#value' => t('Clear'),
    '#name' => 'clear',
  ];

  return system_settings_form($form);
}
