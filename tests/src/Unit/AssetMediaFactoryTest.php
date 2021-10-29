<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\media_acquiadam\AssetDataInterface;
use Drupal\media_acquiadam\MediaEntityHelper;
use Drupal\media_acquiadam\Service\AssetFileEntityHelper;
use Drupal\media_acquiadam\Service\AssetMediaFactory;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamMockedMediaEntityTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Class AssetMediaFactoryTest.
 *
 * Test our factory service to validate its ability to get media information
 * based on asset ID.
 *
 * @group media_acquiadam
 */
class AssetMediaFactoryTest extends UnitTestCase {

  use AcquiadamAssetDataTrait, AcquiadamMockedMediaEntityTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The asset media factory.
   *
   * @var \Drupal\media_acquiadam\Service\AssetMediaFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $assetMediaFactory;

  /**
   * A mock media entity.
   *
   * @var \Drupal\media\MediaInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mediaEntity;

  /**
   * Validate that we get a wrapped media entity.
   */
  public function testGetEntityWrapper() {
    $this->assertInstanceOf($this->assetMediaFactory->getAssetMediaEntityHelperClass(),
      $this->assetMediaFactory->get($this->mediaEntity));
  }

  /**
   * Validate we can change the AssetMediaEntityHelper class.
   */
  public function testGetSetAssetMediaEntityHelperClass() {
    $this->assertEquals(MediaEntityHelper::class,
      $this->assetMediaFactory->getAssetMediaEntityHelperClass());

    $this->assetMediaFactory->setAssetMediaEntityHelperClass(\Drupal::class);
    $this->assertEquals(\Drupal::class,
      $this->assetMediaFactory->getAssetMediaEntityHelperClass());

    $this->assetMediaFactory->setAssetMediaEntityHelperClass(MediaEntityHelper::class);
    $this->assertEquals(MediaEntityHelper::class,
      $this->assetMediaFactory->getAssetMediaEntityHelperClass());
  }

  /**
   * Validate we can get a media source based on an asset Id.
   */
  public function testGetMediaSource() {
    $asset = $this->getAssetData();
    $this->assertInstanceOf(MediaSourceInterface::class,
      $this->assetMediaFactory->getMediaSource($asset->id));
    $this->assertInstanceOf(MediaSourceInterface::class,
      $this->assetMediaFactory->getMediaSource($asset->id, 'acquiadam_asset'));
    $this->assertInstanceOf(MediaSourceInterface::class,
      $this->assetMediaFactory->getMediaSource($asset->id, 'acquiadam_image'));
    $this->assertFalse($this->assetMediaFactory->getMediaSource($asset->id,
      'acquiadam_other'));
    $this->assertFalse($this->assetMediaFactory->getMediaSource(FALSE));
    $this->assertFalse($this->assetMediaFactory->getMediaSource(FALSE,
      'acquiadam_asset'));
  }

  /**
   * Validate we can get a media entity from an asset ID.
   */
  public function testGetMediaEntity() {
    $asset = $this->getAssetData();

    $this->assertInstanceOf(MediaInterface::class,
      $this->assetMediaFactory->getMediaEntity($asset->id));
    $this->assertInstanceOf(MediaInterface::class,
      $this->assetMediaFactory->getMediaEntity($asset->id, 'acquiadam_asset'));
    $this->assertFalse($this->assetMediaFactory->getMediaEntity($asset->id,
      'acquiadam_other'));
    $this->assertFalse($this->assetMediaFactory->getMediaEntity(FALSE,
      'acquiadam_asset'));
  }

  /**
   * Validate we can retrieve multiple entities by asset ID.
   */
  public function testGetMediaEntities() {
    $asset = $this->getAssetData();

    $entities = $this->assetMediaFactory->getMediaEntities($asset->id);
    $this->assertArrayHasKey('acquiadam_asset', $entities);
    $this->assertArrayHasKey('acquiadam_image', $entities);
    $this->assertArrayNotHasKey('acquiadam_other', $entities);
    $this->assertCount(2, $entities);

    $entities = $this->assetMediaFactory->getMediaEntities($asset->id,
      'acquiadam_asset');
    $this->assertCount(1, $entities);
    $this->assertArrayHasKey('acquiadam_asset', $entities);

    $this->assertFalse($this->assetMediaFactory->getMediaEntity($asset->id,
      'acquiadam_other'));
    $this->assertFalse($this->assetMediaFactory->getMediaEntity(FALSE,
      'acquiadam_asset'));
  }

  /**
   * Validate that we can get media entities assets are attached to.
   */
  public function testGetAssetUsage() {
    $asset = $this->getAssetData();

    $this->assertArrayHasKey('acquiadam_asset',
      $this->assetMediaFactory->getAssetUsage($asset->id));
    $this->assertArrayHasKey('acquiadam_asset',
      $this->assetMediaFactory->getAssetUsage($asset->id, 'acquiadam_asset'));
    $this->assertEquals($this->mediaEntity->id(),
      $this->assetMediaFactory->getAssetUsage($asset->id,
        'acquiadam_asset')['acquiadam_asset'][0]);

    $this->assertEmpty($this->assetMediaFactory->getAssetUsage($asset->id,
      'acquiadam_other'));
    $this->assertEmpty($this->assetMediaFactory->getAssetUsage(FALSE,
      'acquiadam_asset'));
    $this->assertEmpty($this->assetMediaFactory->getAssetUsage(FALSE,
      'acquiadam_other'));
  }

  /**
   * Validate we can get configured asset ID fields.
   */
  public function testGetAssetIdFields() {
    $fields = $this->assetMediaFactory->getAssetIdFields();
    $this->assertArrayHasKey('acquiadam_asset', $fields);
    $this->assertArrayHasKey('acquiadam_image', $fields);
    $this->assertArrayNotHasKey('acquiadam_other', $fields);
    $this->assertEquals('phpunit_asset_id_field', $fields['acquiadam_asset']);
    $this->assertEquals('phpunit_asset_id_field', $fields['acquiadam_image']);
  }

  /**
   * Validate we can get a file entity for an asset.
   */
  public function testGetFileEntity() {
    $asset = $this->getAssetData();

    $this->assertInstanceOf(FileInterface::class,
      $this->assetMediaFactory->getFileEntity($asset->id));
    $this->assertInstanceOf(FileInterface::class,
      $this->assetMediaFactory->getFileEntity($asset->id, 'acquiadam_asset'));
    $this->assertInstanceOf(FileInterface::class,
      $this->assetMediaFactory->getFileEntity($asset->id, 'acquiadam_image'));
    $this->assertFalse($this->assetMediaFactory->getFileEntity(FALSE));
    $this->assertFalse($this->assetMediaFactory->getFileEntity($asset->id,
      'acquiadam_other'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $asset = $this->getAssetData();

    $this->mediaEntity = $this->getMockedMediaEntity($asset->id);

    $media_bundle = $this->getMockBuilder(MediaTypeInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $media_bundle->method('getSource')->willReturn($this->mediaEntity->getSource());
    $media_bundle->method('getFieldMap')
      ->willReturn(['file' => 'phpunit_file_field']);

    $entity_storage = $this->getMockBuilder(EntityStorageInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_storage->method('loadByProperties')
      ->with(['source' => 'acquiadam_asset'])
      ->willReturn([
        'acquiadam_asset' => $media_bundle,
        'acquiadam_image' => $media_bundle,
      ]);
    $entity_storage->method('load')->willReturnMap([
      [$this->mediaEntity->id(), $this->mediaEntity],
      [$this->getMockedFileEntity()->id(), $this->getMockedFileEntity()],
      ['media_acquiadam', $media_bundle],
    ]);

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['media_type', $entity_storage],
      ['media', $entity_storage],
      ['file', $entity_storage],
    ]);

    $asset_data = $this->getMockBuilder(AssetDataInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $acquiadam_client = $this->getMockBuilder(AcquiadamInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $asset_file_helper = $this->getMockBuilder(AssetFileEntityHelper::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('media_acquiadam.asset_data', $asset_data);
    $this->container->set('media_acquiadam.acquiadam', $acquiadam_client);
    $this->container->set('media_acquiadam.asset_file.helper',
      $asset_file_helper);
    \Drupal::setContainer($this->container);

    $this->assetMediaFactory = $this->getMockBuilder(AssetMediaFactory::class)
      ->setConstructorArgs([
        $this->container->get('entity_type.manager'),
      ])
      ->setMethods(['getMediaBundleFields'])
      ->getMock();

    $this->assetMediaFactory->method('getMediaBundleFields')->willReturnMap([
      [
        'acquiadam_asset',
        'phpunit_asset_id_field',
        $asset->id,
        [$this->mediaEntity->getRevisionId() => $this->mediaEntity->id()],
      ],
      [
        'acquiadam_image',
        'phpunit_asset_id_field',
        $asset->id,
        [$this->mediaEntity->getRevisionId() => $this->mediaEntity->id()],
      ],
    ]);
  }

}
