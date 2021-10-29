<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\media_acquiadam\MediaEntityHelper;
use Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset;
use Drupal\media_acquiadam\Service\AssetImageHelper;
use Drupal\media_acquiadam\Service\AssetMediaFactory;
use Drupal\media_acquiadam\Service\AssetMetadataHelper;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamMockedMediaEntityTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests to validate that the Media source plugin works as expected.
 *
 * @group media_acquiadam
 */
class AcquiadamAssetTest extends UnitTestCase {

  use AcquiadamAssetDataTrait, AcquiadamConfigTrait, AcquiadamMockedMediaEntityTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Acquia DAM media source.
   *
   * @var \Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset
   */
  protected $acquiadamMediaSource;

  /**
   * Validate that we can get generated metadata.
   */
  public function testGetMetadata() {
    $media = $this->getMockedMediaEntity($this->getAssetData()->id);

    $this->assertEquals('media:media_acquiadam:' . $media->uuid(),
      $this->acquiadamMediaSource->getMetadata($media, 'default_name'));
    $this->assertEquals($this->getMockedFileEntity()->id(),
      $this->acquiadamMediaSource->getMetadata($media, 'file'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $entity_field_manager = $this->getMockBuilder(EntityFieldManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $field_type_plugin_manager = $this->getMockBuilder(FieldTypePluginManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $asset_image_helper = $this->getMockBuilder(AssetImageHelper::class)
      ->disableOriginalConstructor()
      ->getMock();

    $asset_metadata_helper = $this->getMockBuilder(AssetMetadataHelper::class)
      ->disableOriginalConstructor()
      ->getMock();

    $asset_media_helper = $this->getMockBuilder(MediaEntityHelper::class)
      ->disableOriginalConstructor()
      ->getMock();
    $asset_media_helper->method('getAsset')->willReturn($this->getAssetData());
    $asset_media_helper->method('getFile')
      ->willReturn($this->getMockedFileEntity());

    $asset_media_factory = $this->getMockBuilder(AssetMediaFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $asset_media_factory->method('get')->willReturn($asset_media_helper);

    $this->container = new ContainerBuilder();
    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('entity_field.manager', $entity_field_manager);
    $this->container->set('plugin.manager.field.field_type',
      $field_type_plugin_manager);
    $this->container->set('config.factory', $this->getConfigFactoryStub());

    $this->container->set('media_acquiadam.asset_image.helper',
      $asset_image_helper);
    $this->container->set('media_acquiadam.asset_metadata.helper',
      $asset_metadata_helper);
    $this->container->set('media_acquiadam.asset_media.factory',
      $asset_media_factory);
    \Drupal::setContainer($this->container);

    $this->acquiadamMediaSource = AcquiadamAsset::create($this->container,
      ['source_field' => 'field_acquiadam_asset_id'],
      'acquiadam_asset',
      []);
  }

}
