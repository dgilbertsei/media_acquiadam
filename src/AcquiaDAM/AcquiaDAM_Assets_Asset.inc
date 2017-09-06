<?php

/**
 * Generic AcquiaDAM assets.
 *
 * @package AcquiaDAM
 */
class AcquiaDAM_Assets_Asset extends AcquiaDAM_Assets_AbstractAsset {

  /**
   * Return the API endpoint base.
   *
   * {@inheritDoc}
   *
   * @return string
   *   The base slug to use in API requests.
   */
  protected function getEndpointBase() {
    return 'assets';
  }

  /**
   * Get the asset type identifier.
   *
   * {@inheritDoc}
   *
   * @return string
   *   The asset type machine name.
   */
  public function getType() {
    return 'asset';
  }

  /**
   * Get metadata for the asset.
   *
   * @param string $type
   *   The metadata type to return: exif, xmp.
   *
   * @return array
   *   The metadata request response.
   */
  protected function getMetadata($type = 'exif') {
    $this->requireId();
    return $this->request(sprintf('%s/%d/metadatas/%s', $this->getEndpointBase(), $this->assetId, $type));
  }

  /**
   * Get XMP metadata for the asset.
   *
   * @return array
   *   The metadata request response.
   *
   * @see AcquiaDAM_Assets_Asset::getMetadata()
   */
  public function getXMP() {
    return $this->getMetadata('xmp');
  }

  /**
   * Get EXIF metadata for the asset.
   *
   * @return array
   *   The metadata request response.
   *
   * @see AcquiaDAM_Assets_Asset::getMetadata()
   */
  public function getEXIF() {
    return $this->getMetadata('exif');
  }

  /**
   * Get the asset download URL.
   *
   * @param array $options
   *   An array of options to pass to the API call.
   *
   * @return array|false
   *   The request response or FALSE on failure.
   */
  public function getDownload(array $options = []) {

    $this->requireId();

    $options += [
      'sendNotificationsOff' => TRUE,
      'trackDownloadsOff' => TRUE,
      'geturl' => FALSE,
    ];

    return $this->request(sprintf('%s/%d/download', $this->getEndpointBase(), $this->asset['id']), $options);
  }

  /**
   * Get the asset download URL.
   *
   * @return string|false
   *   The asset download URL or FALSE on failure.
   */
  public function getDownloadUrl() {

    $result = $this->getDownload(['geturl' => TRUE]);

    return empty($result['url']) ? FALSE : $result['url'];
  }
}
