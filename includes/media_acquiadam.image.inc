<?php

/**
 * @file
 * Code for working with DAM image styles.
 */

/**
 * Implementation of image_style_deliver().
 *
 * Allows serving images from outside of the file_managed table.
 *
 * @param string $styleName
 *   The image style being requested.
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The asset to view an image of.
 *
 * @return int
 *   A menu response code.
 */
function media_acquiadam_image_style_deliver($styleName, AcquiaDAM_Assets_AbstractAsset $asset) {

  // Drupal does not allow multiple _load style menu arguments so we need to
  // manually load this here.
  $style = image_style_load($styleName);
  if (empty($style)) {
    return MENU_NOT_FOUND;
  }

  try {
    $image_uri = $asset->getThumbnailUrl();
    if (empty($image_uri)) {
      return MENU_NOT_FOUND;
    }

    // Prime image styles before loading below.
    media_acquiadam_create_asset_image_derivatives($asset);
  }
  catch (Exception $x) {
    watchdog_exception('media_acquiadam', $x);
    return MENU_NOT_FOUND;
  }

  $derivative_uri = media_acquiadam_image_style_path($asset['id'], $style, $image_uri);

  // The rest of the logic is the same as contined within the original
  // remote_stream_wrapper function.

  // Don't start generating the image if the derivative already exists or if
  // generation is in progress in another thread.
  $lock_name = 'image_style_deliver:' . $style['name'] . ':' . drupal_hash_base64($image_uri);
  if (!is_file($derivative_uri)) {
    $lock_acquired = lock_acquire($lock_name);
    if (!$lock_acquired) {
      // Tell client to retry again in 3 seconds. Currently no browsers are known
      // to support Retry-After.
      drupal_add_http_header('Status', '503 Service Unavailable');
      drupal_add_http_header('Retry-After', 3);
      print t('Image generation in progress. Try again shortly.');
      drupal_exit();
    }
  }

  // Try to generate the image, unless another thread just did it while we were
  // acquiring the lock.
  $success = is_file($derivative_uri) || image_style_create_derivative($style, $image_uri, $derivative_uri);

  if (!empty($lock_acquired)) {
    lock_release($lock_name);
  }

  if ($success) {
    $image = image_load($derivative_uri);
    file_transfer($image->source, array('Content-Type' => $image->info['mime_type'], 'Content-Length' => $image->info['file_size']));
  }
  else {
    watchdog('media_acquiadam', 'Unable to generate the derived image located at %path.', ['%path' => $derivative_uri]);
    drupal_add_http_header('Status', '500 Internal Server Error');
    print t('Error generating image.');
    drupal_exit();
  }
}

/**
 * Generate all image derivatives for an asset.
 *
 * @param AcquiaDAM_Assets_AbstractAsset $asset
 *   The asset to generate derivatives for.
 *
 * @return bool
 *   TRUE on success, FALSE on failure.
 */
function media_acquiadam_create_asset_image_derivatives(AcquiaDAM_Assets_AbstractAsset $asset) {

  try {
    $thumbnail = $asset->getThumbnailUrl();
  }
  catch (Exception $x) {
    watchdog_exception('media_acquiadam', $x);
    return FALSE;
  }

  $derivatives = [];
  $styles = image_styles();
  foreach ($styles as $style) {
    $uri = media_acquiadam_image_style_path($asset['id'], $style, $thumbnail);
    // Only try and create derivatives that do not exist.
    if (!is_file($uri)) {
      $derivatives[] = [
        'uri' => $uri,
        'style' => $style,
      ];
    }
  }

  if (!empty($derivatives)) {
    // We only want to make this call if there is work to do, otherwise it
    // severely impacts page load performance.
    $image_uri = system_retrieve_file($thumbnail, file_directory_temp());
    if (!empty($image_uri)) {
      foreach ($derivatives as $derivative) {
        image_style_create_derivative($derivative['style'], $image_uri, $derivative['uri']);
      }
      file_unmanaged_delete_recursive($image_uri);
    }
    else {
      watchdog('media_acquiadam',
        'Unable to retrieve @thumbnail for @id to create image derivatives.', [
          '@thumbnail' => $thumbnail,
          '@id' => $asset['id'],
        ],
        WATCHDOG_NOTICE);
      return FALSE;
    }
  }

  return TRUE;
}

/**
 * Generate the destination URI for the derivative.
 *
 * @param int $asset_id
 *   The ID of the asset deriviatives are being created for.
 * @param array $style
 *   The image style information.
 * @param string $image_uri
 *   The image URI of the asset.
 *
 * @return string
 *   The destination URI.
 */
function media_acquiadam_image_style_path($asset_id, array $style, $image_uri) {
  return vsprintf('%s://styles/%s/%d/%s', [
    file_default_scheme(),
    $style['name'],
    $asset_id,
    file_uri_target($image_uri),
  ]);
}
