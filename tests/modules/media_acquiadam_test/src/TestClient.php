<?php

namespace Drupal\media_acquiadam_test;

use Drupal\media_acquiadam\Entity\Asset;

/**
 * Overridden implementation of the Acquia DAM client for testing.
 */
class TestClient {

  /**
   * Array with test asset data.
   *
   * @var array
   */
  protected $testAssets = [];

  /**
   * Add or modify a test asset.
   *
   * @param \Drupal\media_acquiadam\Entity\Asset $asset
   *   The asset to add/modify.
   */
  public function addAsset(Asset $asset) {
    $this->testAssets[$asset->id] = $asset;
  }

  /**
   * Get a test Asset given an Asset ID.
   *
   * @param int $assetId
   *   The test Asset ID.
   *
   * @return \Drupal\media_acquiadam\Entity\Asset
   *   The test asset.
   */
  public function getAsset($assetId) {
    return $this->testAssets[$assetId];
  }

}
