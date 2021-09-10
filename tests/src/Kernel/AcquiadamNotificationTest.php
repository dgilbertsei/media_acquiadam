<?php

namespace Drupal\Tests\acquiadam\Kernel;

use cweagans\webdam\Entity\Asset;
use Drupal\media\Entity\Media;

/**
 * Tests integration with notification API.
 *
 * @group acquiadam
 */
class AcquiadamNotificationTest extends AcquiadamKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->enableNotificationSync();
  }

  /**
   * Tests if assets are updated properly using notification sync.
   */
  public function testNotifications() {

    $test_asset_data = [
      [
        'id' => 3455969,
        'filename' => 'file1.jpg',
        'bundle' => self::DEFAULT_BUNDLE,
      ],
      // Test asset sync with multiple bundles.
      [
        'id' => 3455970,
        'filename' => 'file2.jpg',
        'bundle' => 'acquia_dam_other_asset',
      ],
      [
        'id' => 3455971,
        'filename' => 'file3.jpg',
        'bundle' => self::DEFAULT_BUNDLE,
      ],
    ];
    $asset_ids_to_update = [3455969, 3455970];

    // Add test assets to test client and create media entities.
    $mids = [];
    foreach ($test_asset_data as $test_asset) {
      $bundle = $test_asset['bundle'];
      unset($test_asset['bundle']);
      $this->testClient->addAsset($this->getAssetData($test_asset));
      $mids[] = $this->createMedia($test_asset['id'], $bundle)->id();
    }

    // Generate a new version and notification for some of the assets.
    foreach ($asset_ids_to_update as $asset_id) {
      $this->generateNewVersionAndNotify($this->testClient->getAsset($asset_id));
    }

    // Runs cron to get notifications and update media entities.
    $this->container->get('cron')->run();

    // Asserts that media entities were updated properly.
    foreach (Media::loadMultiple($mids) as $media) {
      $asset_id = (int) $media->get('field_acquiadam_asset_id')->getString();
      $asset = $this->testClient->getAsset($asset_id);
      $this->assertEqual($media->label(), $asset->filename, 'Media entity updated correctly.');
    }
  }

  /**
   * Enable notification sync.
   */
  protected function enableNotificationSync() {
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('acquiadam.settings');
    $config->set('sync_interval', -1);
    $config->set('notifications_sync', 1);
    $config->save(TRUE);
  }

  /**
   * Updates an Asset and add notification to it.
   *
   * @param \cweagans\webdam\Entity\Asset $asset
   *   The asset to be updated.
   */
  protected function generateNewVersionAndNotify(Asset $asset) {
    $this->generateNewVersion($asset);
    $this->testClient->addNotification([
      'action' => 'asset_version',
      'source' => [
        'type' => 'asset',
        'id' => $asset->id,
      ],
    ]);
  }

}
