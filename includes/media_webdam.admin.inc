<?php

/**
 * @file
 * Administration page callbacks for the Media: Webdam module.
 */

/**
 * Displays the media administration page.
 */
function media_webdam_config_settings($form, &$form_state) {

  $form['auth'] = [
    '#type' => 'fieldset',
    '#title' => t('Authentication'),
    '#description' => t('Settings pertinent to API authentication.'),
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
    '#tree' => FALSE,
  ];

  $form['auth']['media_webdam_client_id'] = [
    '#type' => 'textfield',
    '#title' => t('Client ID'),
    '#description' => t('Please contact your Customer Success Manager, Implementation Consultant, or file a ticket with the <a href="https://www.damsuccess.com/hc/en-us/requests/new">support team</a> for the CLIENT_ID.'),
    '#default_value' => variable_get('media_webdam_client_id', ''),
    '#required' => TRUE,
  ];

  $form['auth']['media_webdam_client_secret'] = [
    '#type' => 'textfield',
    '#title' => t('Client Secret'),
    '#description' => t('Please contact your Customer Success Manager, Implementation Consultant, or file a ticket with the <a href="https://www.damsuccess.com/hc/en-us/requests/new">support team</a> for the CLIENT_Secret.'),
    '#default_value' => variable_get('media_webdam_client_secret', ''),
    '#required' => TRUE,
  ];

  return system_settings_form($form);
}
