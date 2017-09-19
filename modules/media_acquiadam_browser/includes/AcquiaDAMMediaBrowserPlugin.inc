<?php

/**
 * @file
 * Definition of AcquiaDAMMediaBrowserPlugin.
 */

/**
 * Media browser plugin for showing the upload form.
 *
 * @deprecated
 */
class AcquiaDAMMediaBrowserPlugin extends MediaBrowserPlugin {

  /**
   * Implements MediaBrowserPlugin::view().
   *
   * {@inheritDoc}
   */
  public function view() {
    module_load_include('module', 'media_acquiadam', 'media_acquiadam');
    $build = [
      '#title' => t('Acquia DAM'),
      '#weight' => 10,
      'form' => drupal_get_form('media_acquiadam_browser_choose_asset_form', $this->params),
    ];
    return $build;
  }

  /**
   * Implements MediaBrowserPlugin::access().
   *
   * {@inheritDoc}
   */
  public function access($account = NULL) {
    return user_access('administer files', $account) || user_access('access media acquiadam browser', $account);
  }

}
