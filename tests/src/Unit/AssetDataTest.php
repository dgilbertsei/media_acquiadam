<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\media_acquiadam\AssetData;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\UnitTestCase;

class AssetDataTest extends UnitTestCase {

  use AcquiadamAssetDataTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Media: Acquia DAM asset data service.
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
    $this->assertTrue($this->acquiaAssetData->isUpdatedAsset($asset));
    $asset->id = 3455970;
    $this->assertFalse($this->acquiaAssetData->isUpdatedAsset($asset));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $connection = $this->getMockBuilder(Connection::class)
      ->disableOriginalConstructor()
      ->getMock();

    $asset_data = $this->getMockBuilder(AssetData::class)
      ->disableOriginalConstructor()
      ->setMethods(['get', 'set'])
      ->getMock();
    $asset_data->method('get')->willReturnMap([
      [3455969, 'version', 3],
      [3455970, 'version', 4],
    ]);

    $this->acquiaAssetData = $asset_data;

    $this->container = new ContainerBuilder();
    $this->container->set('database', $connection);
    Drupal::setContainer($this->container);
  }

}
