<?php

/**
 * @file
 * Create an AcquiaDAM Stream Wrapper class for the Media/Resource module.
 */

/**
 * Provides a remote stream wrapper for AcquiaDAM assets.
 *
 * Create an instance like this:
 * ```php
 *   $dam = new AcquiaDAMStreamWrapper('acquiadam://[asset_id].[filetype]');
 * ```
 */
class AcquiaDAMStreamWrapper extends DrupalRemoteStreamWrapper {

  /**
   * Fetch the content of the file using drupal_http_request().
   */
  protected function getStreamContent() {
    if (!isset($this->stream_content)) {
      $this->stream_content = NULL;

      // Translate our acquiadam:// uri into a remote URL before we make the
      // request.
      $uri = file_create_url($this->uri);

      $request = drupal_http_request($uri);
      if (empty($request->error) && !empty($request->data)) {
        $this->stream_content = $request->data;
      }
      elseif (!empty($request->error)) {
        watchdog('media_acquiadam',
          '(@code) Error fetching asset content: @error',
          [
            '@code' => $request->code,
            '@error' => $request->error,
          ],
          WATCHDOG_NOTICE,
          l(t('View'), $uri));
      }
    }

    return $this->stream_content;
  }

  /**
   * Support for fstat().
   *
   * {@inheritDoc}
   */
  public function stream_stat() {

    $stat = [];

    // Skip looking up the size if we already have it stored locally.
    // If we don't do this then file listing pages can issue several requests
    // and have a delay in loading.
    $files = entity_load('file', FALSE, array('uri' => $this->uri));
    $file = !empty($files) ? reset($files) : FALSE;
    if (!empty($file->filesize)) {
      $stat = ['size' => $file->filesize];
    }
    else {
      // If the asset has an overall large size then we skip trying to get the
      // accurate size of the specific URL we're referencing. This comes into
      // play when the source image or video might be 15MB but we're trying to
      // render a thumbnail.
      $asset = $this->getAssetByUri($this->uri);
      // Filesize can be '0.00' which will count as not empty, so we have to
      // check the size is greater than 0 as well.
      if (!empty($asset['filesize']) && 0 < $asset['filesize']) {
        $stat['size'] = $asset['filesize'] * 1024 * 1024;
      }
      else {
        // Translate our acquiadam:// uri into a remote URL before we make the
        // request.
        $uri = file_create_url($this->uri);

        $scheme = file_uri_scheme($uri);
        if (empty($scheme)) {
          $uri = url($uri, ['absolute' => TRUE]);
        }

        // We have to use a GET request here instead of HEAD because the
        // download links we receive are not valid for a HEAD-type request and
        // will return a 403.
        $request = drupal_http_request($uri, array('method' => 'GET'));
        if (empty($request->error) && isset($request->headers['content-length'])) {
          $stat['size'] = $request->headers['content-length'];
        }
        elseif (!empty($request->error)) {
          watchdog('media_acquiadam',
            '(@code) Error fetching asset size: @error',
            [
              '@code' => $request->code,
              '@error' => $request->error,
            ],
            WATCHDOG_NOTICE,
            l(t('View'), $uri));
        }
      }
    }

    return !empty($stat) ? $this->getStat($stat) : FALSE;
  }

  /**
   * Get the mimetype of the file.
   *
   * Overridden to work with the AcquiaDAM uri structure.
   *
   * {@inheritDoc}
   */
  public static function getMimeType($uri, $mapping = NULL) {

    if (!isset($mapping)) {
      // The default file map, defined in file.mimetypes.inc is quite big.
      // We only load it when necessary.
      include_once DRUPAL_ROOT . '/includes/file.mimetypes.inc';
      $mapping = file_mimetype_mapping();
    }

    if ($target = file_uri_target($uri)) {
      $extension = '';
      $file_parts = explode('.', drupal_basename($target));

      // Remove the first part: a full filename should not match an extension.
      array_shift($file_parts);

      // Iterate over the file parts, trying to find a match.
      // For my.awesome.image.jpeg, we try:
      // - jpeg
      // - image.jpeg, and
      // - awesome.image.jpeg
      while ($additional_part = array_pop($file_parts)) {
        $extension = strtolower($additional_part . ($extension ? '.' . $extension : ''));
        if (isset($mapping['extensions'][$extension])) {
          return $mapping['mimetypes'][$mapping['extensions'][$extension]];
        }
      }
    }

    return 'application/octet-stream';
  }

  /**
   * Return the external Url.
   *
   * {@inheritDoc}
   */
  public function getExternalUrl() {

    if ('acquiadam' !== file_uri_scheme($this->uri)) {
      return $this->uri;
    }

    $asset = static::getAssetByUri($this->uri);
    if (empty($asset)) {
      return FALSE;
    }
    $preview = $asset->getPreviewUrl();

    if (empty($preview)) {
      // All other files (txt, docx, html, etc.).
      return url('acquiadam/asset/' . $asset['id'], ['absolute' => TRUE]);
    }

    return $preview;
  }

  /**
   * Get an asset ID from an AcquiaDAM Uri.
   *
   * @param string $uri
   *   The URI to parse for an asset ID.
   *
   * @return int|false
   *   The asset ID or FALSE on failure.
   */
  protected static function getAssetIdFromUri($uri) {
    $target = file_uri_target($uri);
    list($asset_id) = explode('.', drupal_basename($target), 2);
    $asset_id = intval($asset_id);

    return empty($asset_id) ? FALSE : $asset_id;
  }

  /**
   * Load an asset by the Uri.
   *
   * @param string $uri
   *   The Uri to use to look up an asset.
   *
   * @return AcquiaDAM_Assets_Asset|false
   *   The asset or FALSE on failure.
   */
  protected static function getAssetByUri($uri) {

    $asset_id = static::getAssetIdFromUri($uri);
    return static::getAssetById($asset_id);
  }

  /**
   * Get an asset by ID.
   *
   * This requires the file already exist in Drupal and has valid JSON saved
   * to the database. We cannot directly look up assets using the API because
   * there is no promise that the user has API access.
   *
   * @param int $id
   *   The asset ID.
   *
   * @return AcquiaDAM_Assets_Asset|false
   *   The asset or FALSE on failure.
   */
  protected static function getAssetById($id) {

    module_load_include('inc', 'media_acquiadam', 'includes/media_acquiadam.helpers');
    try {
      $asset = media_acquiadam_api_get_asset($id);
    }
    catch (Exception $x) {
      watchdog_exception('acquiadam_stream_wrapper', $x);
      drupal_set_message(t('@class: Unable to load remote asset: @id.', [
        '@class' => __CLASS__,
        '@id' => $id,
      ]), 'warning');
    }

    return $asset;
  }

}
