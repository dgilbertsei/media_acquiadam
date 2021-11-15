<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\media_acquiadam\AssetData;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests to validate that the asset data service works as expected.
 *
 * @group media_acquiadam
 */
class AssetDataTest extends UnitTestCase {

  use AcquiadamAssetDataTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Acquia DAM asset data service.
   *
   * Mocked to have a fixed set/get.
   *
   * @var \Drupal\media_acquiadam\AssetData|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $acquiaAssetData;

  /**
   * Validates that we can correctly determine if an asset has been updated.
   */
  public function testIsUpdatedAsset() {
    $asset = $this->getAssetData();
    $this->assertFalse($this->acquiaAssetData->isUpdatedAsset($asset));
    $asset->file_upload_date = "2021-09-27T12:21:21Z";
    $this->assertTrue($this->acquiaAssetData->isUpdatedAsset($asset));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    $connection = $this->getMockBuilder(Connection::class)
      ->disableOriginalConstructor()
      ->getMock();

    $asset_data = $this->getMockBuilder(AssetData::class)
      ->disableOriginalConstructor()
      ->setMethods(['get', 'set'])
      ->getMock();
    $asset_data->method('get')->willReturnMap([
      ["34asd3q2-e294-4908-bbd9-f43f433d2e23", 'file_upload_date', 1632508262],
      ["34asd3q2-e294-4908-bbd9-f43f433d2e23", 'file_upload_date', 1632508262],
    ]);

    $this->acquiaAssetData = $asset_data;

    $this->container = new ContainerBuilder();
    $this->container->set('database', $connection);
    \Drupal::setContainer($this->container);
  }

}
