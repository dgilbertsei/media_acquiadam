<?php

/**
 * Alter DAM browser assets before displaying them to the user.
 *
 * Note that adding or removing assets here may cause unexpected behavior within
 * the paging mechanism unless adjustments are made.
 *
 * @param array $assets
 *   The array of assets being shown to the user.
 * @param array $context
 *   An array containing information about the DAM browser state.
 */
function HOOK_media_acquiadam_browser_assets_alter(array &$assets, array $context) {
  foreach ($assets as $index => $asset) {
    if (drupal_tolower($asset['filetype']) == 'jpg') {
      unset($assets[$index]);
    }
  }
}

/**
 * Alter the asset properties being displayed within the information modal.
 *
 * @param array $data
 *   An array of properties, metadata, and a preview image.
 * @param array $context
 *   Information about the asset.
 */
function HOOK_media_acquiadam_browser_info_alter(array &$data, array $context) {
  if ('jpg' == drupal_tolower($context['asset']['filetype'])) {
    $data['properties']['custom-property'] = [
      t('A custom property'),
      format_date(REQUEST_TIME),
    ];
  }
}

/**
 * Alter the list of quick links available for a given asset.
 *
 * @param array $links
 *   An array of links as passed to theme('ctools_dropdown', ...).
 * @param array $context
 *   Information about the asset.
 */
function HOOK_media_acquiadam_browser_jump_list_alter(array &$links, array $context) {
  if ('folder' == $context['asset']->getType()) {
    $links[] = [
      'title' => t('This is a folder'),
    ];
  }
}
