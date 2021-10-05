<?php

namespace Drupal\Tests\acquiadam\Unit;

use Drupal\acquiadam\Entity\Asset;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\acquiadam\Acquiadam;
use Drupal\acquiadam\AssetDataInterface;
use Drupal\acquiadam\Client;
use Drupal\Tests\acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\acquiadam\Traits\AcquiadamLoggerFactoryTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Acquia DAM REST extension tests.
 *
 * @group acquiadam
 */
class AcquiadamServiceTest extends UnitTestCase {

  use AcquiadamConfigTrait, AcquiadamLoggerFactoryTrait, AcquiadamAssetDataTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Acquia DAM client.
   *
   * @var \Drupal\acquiadam\Acquiadam
   */
  protected $acquiaDamClient;

  /**
   * Validate the static cache helper works as expected.
   */
  public function testStaticAssetCache() {
    $asset = $this->getAssetData();

    $this->assertNull($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));

    $this->acquiaDamClient->staticAssetCache('set', $asset->id, FALSE);
    $this->assertFalse($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));

    $this->acquiaDamClient->staticAssetCache('set', $asset->id, $asset);
    $this->assertInstanceOf(Asset::class,
      $this->acquiaDamClient->staticAssetCache('get', $asset->id));

    $this->acquiaDamClient->staticAssetCache('clear');
    $this->assertNull($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));
  }

  /**
   * Validates we can fetch an asset.
   */
  public function testGetAsset() {
    $asset = $this->getAssetData();

    // No assets should be primed.
    $this->assertNull($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));

    // Asset should be primed.
    $this->assertInstanceOf(Asset::class,
      $this->acquiaDamClient->getAsset($asset->id));
    $this->assertInstanceOf(Asset::class,
      $this->acquiaDamClient->staticAssetCache('get', $asset->id));

    // Simulate a cached failed fetch.
    $this->acquiaDamClient->staticAssetCache('set', $asset->id, FALSE);
    $this->assertFalse($this->acquiaDamClient->getAsset($asset->id));

  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    $dam_client = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->getMock();
    $dam_client->expects($this->any())
      ->method('getCategoryData')
      ->willReturnCallback(function ($category) {
        return $this->getCategoryData($category);
      });

    $dam_client->expects($this->any())
      ->method('getAsset')
      ->willReturnCallback(function() {
        return $this->getAssetData();
      });
    $acquiadam_asset_data = $this->getMockBuilder(AssetDataInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('logger.factory', $this->getLoggerFactoryStub());
    $this->container->set('acquiadam.client',
      $dam_client);
    $this->container->set('acquiadam.asset_data', $acquiadam_asset_data);
    \Drupal::setContainer($this->container);

    $this->acquiaDamClient = Acquiadam::create($this->container);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    // Reset the static cache because it will persist between tests.
    $this->acquiaDamClient->staticAssetCache('clear');
  }

}
