<?php

namespace Drupal\acquiadam_test;

use cweagans\webdam\Entity\Asset;

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
   * Array with test notifications.
   *
   * @var array
   */
  protected $notifications = [];

  /**
   * Test data for Active XMP fields.
   *
   * @return array
   *   An empty array.
   */
  public function getActiveXmpFields() {
    return [];
  }

  /**
   * Add a test notification.
   *
   * @param array $notification
   *   The test notification to add.
   */
  public function addNotification(array $notification) {
    $this->notifications[] = $notification;
  }

  /**
   * Returns the notifications with the expected format.
   *
   * @return array
   *   The notifications with the expected format.
   */
  public function getNotifications() {
    return [
      'total' => count($this->notifications),
      'notifications' => $this->notifications,
    ];
  }

  /**
   * Add or modify a test asset.
   *
   * @param \cweagans\webdam\Entity\Asset $asset
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
   * @return \cweagans\webdam\Entity\Asset
   *   The test asset.
   */
  public function getAsset($assetId) {
    return $this->testAssets[$assetId];
  }

}
