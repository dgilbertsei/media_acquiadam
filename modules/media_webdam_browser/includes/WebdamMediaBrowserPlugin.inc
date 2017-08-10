<?php

/**
 * @file
 * Definition of WebdamMediaBrowserPlugin.
 */

/**
 * Media browser plugin for showing the upload form.
 *
 * @deprecated
 */
class WebdamMediaBrowserPlugin extends MediaBrowserPlugin {

  /**
   * Implements MediaBrowserPlugin::view().
   *
   * {@inheritDoc}
   */
  public function view() {
    module_load_include('module', 'media_webdam', 'media_webdam');
    $build = [
      '#title' => t('Webdam'),
      '#weight' => 10,
      'form' => drupal_get_form('media_webdam_browser_choose_asset_form', $this->params),
    ];
    return $build;
  }

  /**
   * Implements MediaBrowserPlugin::access().
   *
   * {@inheritDoc}
   */
  public function access($account = NULL) {
    return media_webdam_browser_access($account);
  }

}
