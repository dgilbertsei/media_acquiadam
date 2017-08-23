<?php

/**
 * Generic Webdam assets.
 *
 * @package Webdam
 */
class Webdam_Assets_Asset extends Webdam_Assets_AbstractAsset {

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
   * @see Webdam_Assets_Asset::getMetadata()
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
   * @see Webdam_Assets_Asset::getMetadata()
   */
  public function getEXIF() {
    return $this->getMetadata('exif');
  }

}
