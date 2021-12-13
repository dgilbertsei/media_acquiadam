<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\AssetData;
use Drupal\media_acquiadam\Entity\Asset;
use Drupal\media_acquiadam\MediaEntityHelper;
use Drupal\media_acquiadam\Service\AssetFileEntityHelper;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamMockedMediaEntityTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Testing of the Media Entity helper class.
 *
 * @group media_acquiadam
 */
class MediaEntityHelperTest extends UnitTestCase {

  use AcquiadamAssetDataTrait, AcquiadamMockedMediaEntityTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Validate we can get file from a media entity.
   */
  public function testGetFile() {
    $this->setupApiResponseStub($this->getAssetData()->id, $this->getAssetData());

    $this->assertInstanceOf(FileInterface::class, $this->getNewMediaEntityHelper()->getFile());
  }

  /**
   * Validate we can properly load an existing file.
   */
  public function testGetExistingFile() {
    $this->assertInstanceOf(FileInterface::class,
      $this->getNewMediaEntityHelper()->getExistingFile());

    $media = $this->getMockBuilder(MediaInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var \Drupal\media\MediaInterface|\PHPUnit\Framework\MockObject\MockObject $media */
    $this->assertFalse($this->getNewMediaEntityHelper($media)
      ->getExistingFile());
  }

  /**
   * Validate we can get an existing fild ID if one is present.
   */
  public function testGetExistingFileId() {
    $this->assertEquals($this->getMockedFileEntity()->id(),
      $this->getNewMediaEntityHelper()->getExistingFileId());

    /** @var \Drupal\media\MediaInterface|\PHPUnit\Framework\MockObject\MockObject $media */
    $media = $this->getMockBuilder(MediaInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->assertFalse($this->getNewMediaEntityHelper($media)
      ->getExistingFileId());
  }

  /**
   * Validates we can get the file field assets data is stored in.
   */
  public function testGetAssetFileField() {
    $this->assertEquals('phpunit_file_field',
      $this->getNewMediaEntityHelper()->getAssetFileField());

    /** @var \Drupal\media\MediaInterface|\PHPUnit\Framework\MockObject\MockObject $media */
    $media = $this->getMockBuilder(MediaInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->assertFalse($this->getNewMediaEntityHelper($media)
      ->getAssetFileField());
  }

  /**
   * Validates that we can get the asset.
   */
  public function testGetAsset() {
    $this->setupApiResponseStub($this->getAssetData()->id, $this->getAssetData());
    $this->assertInstanceOf(Asset::class,
      $this->getNewMediaEntityHelper()->getAsset());

    // Change our source field to simulate a missing asset.
    $media = $this->getMockedMediaEntity($this->getAssetData()->id,
      'phpunit_test_fail');
    $this->assertFalse($this->getNewMediaEntityHelper($media)->getAsset());
  }

  /**
   * Validates that we can get the asset ID.
   */
  public function testGetAssetId() {
    $this->assertEquals($this->getAssetData()->id,
      $this->getNewMediaEntityHelper()->getAssetId());

    // Change our source field to simulate a missing asset.
    $media = $this->getMockedMediaEntity($this->getAssetData()->id,
      'phpunit_test_fail');
    $this->assertFalse($this->getNewMediaEntityHelper($media)->getAssetId());
  }

  /**
   * Setups the API response stub.
   *
   * @param string $request_query
   *   The query for the Get Asset Api request.
   * @param mixed $response
   *   The stub of Get Asset API response.
   */
  protected function setupApiResponseStub(string $request_query, $response) {
    $this->acquiadamClient
      ->expects($this->any())
      ->method('getAsset')
      ->with($request_query)
      ->willReturn($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->acquiadamClient = $this->getMockBuilder(Acquiadam::class)
      ->setMethods(['getAsset'])
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->setMockedDrupalServices($this->container);
    $this->setMockedAcquiaDamServices($this->container);
    $this->container->set('media_acquiadam.acquiadam', $this->acquiadamClient);
    \Drupal::setContainer($this->container);
  }

  /**
   * Gets an instance of the MediaEntityHelper class.
   *
   * @param \Drupal\media\MediaInterface|null $media
   *   The media entity to wrap.
   *
   * @return \Drupal\media_acquiadam\MediaEntityHelper
   *   An instance of the MediaEntityHelper class.
   */
  protected function getNewMediaEntityHelper(MediaInterface $media = NULL) {
    if (is_null($media)) {
      $media = $this->getMockedMediaEntity($this->getAssetData()->id);
    }

    return new MediaEntityHelper($media,
      $this->container->get('entity_type.manager'),
      $this->container->get('media_acquiadam.asset_data'),
      $this->container->get('media_acquiadam.acquiadam'),
      $this->container->get('media_acquiadam.asset_file.helper'));
  }

  /**
   * Sets Drupal mocked services into a container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container to set mocks into.
   */
  protected function setMockedDrupalServices(ContainerBuilder $container) {

    $media_bundle = $this->getMockBuilder(\stdClass::class)
      ->setMethods(['getFieldMap'])
      ->getMock();
    $media_bundle->method('getFieldMap')
      ->willReturn(['file' => 'phpunit_file_field']);

    $entity_storage = $this->getMockBuilder(EntityStorageInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_storage->method('load')->willReturnMap([
      [$this->getMockedFileEntity()->id(), $this->getMockedFileEntity()],
      ['media_acquiadam', $media_bundle],
    ]);
    $entity_storage->method('loadByProperties')->willReturnMap([
      [
        ['uri' => 'private://assets/replaced/' . $this->getAssetData()->filename],
        [$this->getMockedFileEntity()],
      ],
      [
        ['uri' => 'private://assets/replaced/Micro turbine 60.jpg'],
        [$this->getMockedFileEntity()],
      ],
    ]);

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['file', $entity_storage],
      ['media_type', $entity_storage],
    ]);

    $container->set('entity_type.manager', $entity_type_manager);
  }

  /**
   * Sets Acquia DAM mocked services into a container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container to set mocks into.
   */
  protected function setMockedAcquiaDamServices(ContainerBuilder $container) {
    $asset_data = $this->getMockBuilder(AssetData::class)
      ->disableOriginalConstructor()
      ->getMock();
    $asset_data->method('isUpdatedAsset')->willReturnOnConsecutiveCalls(FALSE,
      TRUE);

    $acquiadam = $this->getMockBuilder(Acquiadam::class)
      ->disableOriginalConstructor()
      ->getMock();
    $acquiadam->method('getAsset')->willReturnMap([
      [$this->getAssetData()->id, TRUE, $this->getAssetData()],
    ]);

    $asset_file_helper = $this->getMockBuilder(AssetFileEntityHelper::class)
      ->disableOriginalConstructor()
      ->getMock();
    $asset_file_helper->method('getDestinationFromEntity')
      ->willReturn('private://assets/replaced');
    $asset_file_helper->method('createNewFile')->with($this->anything(),
      'private://assets/replaced')->willReturn($this->getMockedFileEntity());

    $container->set('media_acquiadam.asset_data', $asset_data);
    $container->set('media_acquiadam.acquiadam', $acquiadam);
    $container->set('media_acquiadam.asset_file.helper', $asset_file_helper);
  }

}
