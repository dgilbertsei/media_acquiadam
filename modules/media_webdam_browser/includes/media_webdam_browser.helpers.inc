<?php

/**
 * @file
 * Helper functions for the Webdam browser.
 */

/**
 * Gets the parent hierarchy of the given folder.
 *
 * @param int $folderId
 *   The folder ID to start with.
 *
 * @return array
 *   An array of folder IDs from parent to child that includes the original ID.
 */
function media_webdam_browser_get_trail($folderId) {

  $trail = [];

  if (!empty($folderId) && Webdam_Assets_Folder::ROOT_FOLDER_ID != $folderId) {
    try {
      $folder = media_webdam_api_get_folder($folderId);
      $parents = $folder->getParents();
      $parents[] = $folderId;
      $trail = $folder->getMultiple($parents);
    }
    catch (Exception $x) {
      drupal_set_message($x->getMessage(), 'error');
      watchdog_exception('media_webdam_browser', $x);
    }
  }

  return $trail;
}

/**
 * Get the child assets of a given folder.
 *
 * @param int $folderId
 *   The folder id to get assets for. Defaults to the Webdam root folder.
 * @param int $perPage
 *   The number of assets to limit the results to. Defaults to 25. Note: Webdam
 *   does not include folders towards the count of the limit.
 *
 * @return array|bool
 *   An array of assets with the total remote count, or FALSE.
 */
function media_webdam_browser_get_children($folderId = Webdam_Assets_Folder::ROOT_FOLDER_ID, $perPage = 25) {

  $result = [
    'assets' => [],
    'total_count' => 0,
  ];

  $asset_types = [];

  if (Webdam_Assets_Folder::ROOT_FOLDER_ID == $folderId) {

    try {
      $folder = media_webdam_api_get_folder($folderId);
      $root = $folder->getRoot();
    }
    catch (Exception $x) {
      drupal_set_message($x->getMessage(), 'error');
      watchdog_exception('media_webdam_browser', $x);

      return FALSE;
    }

    $asset_types['folders'] = $root;
    $result['total_count'] = count($root);
  }
  else {

    try {
      $folder = media_webdam_api_get_folder($folderId);
      $asset_types = $folder->getAssets(array(
        'limit' => $perPage,
        'offset' => pager_find_page() * $perPage,
      ), TRUE);
      $result['total_count'] = $asset_types['total_count'];
    }
    catch (Exception $x) {
      drupal_set_message($x->getMessage(), 'error');
      watchdog_exception('media_webdam_browser', $x);

      return FALSE;
    }
  }

  // Our API results separate folders from assets but we don't need that
  // distinction on the browser side, so combine them into a singular array and
  // return that instead.
  if (!empty($asset_types['folders'])) {
    // Filter out empty folders.
    $asset_types['folders'] = array_filter($asset_types['folders'], function ($folder) {
      return 0 < ($folder['numassets'] + $folder['numchildren']);
    });
    $result['assets'] = array_merge($result['assets'], $asset_types['folders']);
  }
  if (!empty($asset_types['items'])) {
    $result['assets'] = array_merge($result['assets'], $asset_types['items']);
  }

  return $result;
}

/**
 * Get the initial set of assets to display on the media browser form.
 *
 * @param int $folderId
 *   The folder ID to pull assets from.
 * @param array $form_state
 *   The current form state.
 * @param int $perPage
 *   The amount of assets to show per page.
 *
 * @return array
 *   An array containing a list of assets and the total count of all available
 *   assets.
 */
function media_webdam_browser_get_initial_assets($folderId, array $form_state, $perPage) {

  $qp = drupal_get_query_parameters(NULL, ['q', 'page', 'aa']);

  $result = !empty($qp['search']) ?
    media_webdam_browser_get_search_results($folderId, $qp['search'], $form_state, $perPage) :
    media_webdam_browser_get_children($folderId, $perPage);

  return $result;
}

/**
 * Perform a search against the API.
 *
 * @param int $folderId
 *   The DAM folder ID to limit searching to. Will include subfolders.
 * @param array $search
 *   An array of search properties: keywords, type.
 * @param array $form_state
 *   The current form state array.
 * @param int $perPage
 *   The number of results to limit the search to.
 *
 * @return array
 *   An keyed array featuring assets and total_count.
 */
function media_webdam_browser_get_search_results($folderId, array $search, array $form_state, $perPage = 25) {
  $child_assets = [
    'assets' => [],
    'total_count' => 0,
  ];

  $keywords = !empty($search['keywords']) ? $search['keywords'] : '';
  $types = !empty($search['type']) ? $search['type'] : '';

  try {
    $options = [
      'folderid' => $folderId,
      'limit' => $perPage,
      'offset' => pager_find_page() * $perPage,
      'types' => $types,
    ];
    $searcher = new Webdam_Search();
    $results = $searcher->search($keywords, $options);
  }
  catch (Exception $x) {
    drupal_set_message($x->getMessage(), 'error');
    watchdog_exception('media_webdam_browser', $x);
  }

  if (!empty($results['items'])) {
    foreach ($results['items'] as $asset) {
      $child_assets['assets'][] = media_webdam_api_get_asset($asset);
    }
    $child_assets['total_count'] = $results['total_count'];
  }

  return $child_assets;
}

/**
 * Breaks up the Root folder list into the current page results.
 *
 * @param array $initialAssets
 *   An array of assets.
 * @param int $perPage
 *   The number of assets per page.
 *
 * @return array
 *   An keyed array featuring assets and total_count.
 */
function media_webdam_browser_fake_paged_root(array $initialAssets, $perPage = 25) {
  $result = [
    'assets' => [],
    'total_count' => count($initialAssets['assets']),
  ];

  if (0 < $result['total_count']) {
    $chunked = array_chunk($initialAssets['assets'], $perPage);
    $current = pager_find_page();
    if ($current >= count($chunked)) {
      $current = max(0, count($chunked) - 1);
    }
    $result['assets'] = empty($chunked[$current]) ?
      [] :
      $chunked[$current];
  }

  return $result;
}
