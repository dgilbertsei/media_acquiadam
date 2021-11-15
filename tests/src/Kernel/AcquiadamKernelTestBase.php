<?php

namespace Drupal\Tests\media_acquiadam\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media_acquiadam\AssetData;
use Drupal\media_acquiadam\Client;
use Drupal\media_acquiadam\Entity\Asset;
use Drupal\media_acquiadam\Service\AssetFileEntityHelper;
use Drupal\media_acquiadam_test\TestClient;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;

/**
 * Base class for Acquia DAM kernel tests.
 */
abstract class AcquiadamKernelTestBase extends EntityKernelTestBase {

  use AcquiadamAssetDataTrait;

  const DEFAULT_BUNDLE = 'acquia_dam_asset';

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
    'fallback_formatter',
    'file',
    'image',
    'media',
    'media_acquiadam',
    'media_acquiadam_test',
  ];

  /**
   * The test client.
   *
   * @var \Drupal\media_acquiadam_test\TestClient
   */
  protected $testClient;

  /**
   * Acquia DAM asset data service.
   *
   * Mocked to have a fixed set/get.
   *
   * @var \Drupal\media_acquiadam\AssetData|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $acquiaAssetData;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();

    $this->setTestClient();

    $this->installConfig('media_acquiadam_test');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('media_acquiadam', ['acquiadam_assets_data']);
  }

  /**
   * Sets a test client for this test.
   */
  protected function setTestClient() {
    $this->testClient = new TestClient();

    $acquiadam_client_factory = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->getMock();
    $acquiadam_client_factory->expects($this->any())
      ->method('getAsset')
      ->willReturnCallback(function ($assetId) {
        return $this->testClient->getAsset($assetId);
      });
    $acquiadam_client_factory->expects($this->any())
      ->method('getSpecificMetadataFields')
      ->willReturn([
        'author' => [
          'label' => "author",
          'type' => "string",
        ],
      ]);
    $this->container->set('media_acquiadam.client',
      $acquiadam_client_factory);

    $asset_data = $this->getMockBuilder(AssetData::class)
      ->disableOriginalConstructor()
      ->setMethods(['get', 'set', 'isUpdatedAsset'])
      ->getMock();
    $asset_data->expects($this->any())
      ->method('get')->willReturn(function ($assetId, $name) {
        return $this->asset->${name};
      });
    $asset_data->expects($this->any())
      ->method('isUpdatedAsset')->willReturn(TRUE);
    $this->container->set('media_acquiadam.asset_data', $asset_data);
    $this->acquiaAssetData = $asset_data;

    $fileHelper = $this->getMockBuilder(AssetFileEntityHelper::class)
      ->setConstructorArgs([
        $this->container->get('entity_type.manager'),
        $this->container->get('entity_field.manager'),
        $this->container->get('config.factory'),
        $this->container->get('file_system'),
        $this->container->get('token'),
        $this->container->get('media_acquiadam.asset_image.helper'),
        $this->container->get('media_acquiadam.acquiadam'),
        $this->container->get('media_acquiadam.asset_media.factory'),
        $this->container->get('logger.factory'),
      ])
      ->setMethods([
        'phpFileGetContents',
      ])
      ->getMock();
    $fileHelper->expects($this->any())->method('phpFileGetContents')->willReturn('File contents');
    $this->container->set('media_acquiadam.asset_file.helper', $fileHelper);
    \Drupal::setContainer($this->container);
  }

  /**
   * Creates a media entity with a given Asset ID.
   *
   * @param string $asset_id
   *   The asset ID.
   * @param string $bundle
   *   The media entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\media\Entity\Media
   *   The created media entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createMedia(string $asset_id, string $bundle = self::DEFAULT_BUNDLE) {
    $media = Media::create([
      'bundle' => $bundle,
      'field_acquiadam_asset_id' => $asset_id,
    ]);
    $media->save();
    return $media;
  }

  /**
   * Get asset file entity from Media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to get the file from.
   *
   * @return mixed
   *   The referenced file entity.
   */
  public function getAssetFileEntity(MediaInterface $media) {
    return current($media
      ->get('field_acquiadam_asset_file')
      ->referencedEntities());
  }

  /**
   * Get the URI from a given asset.
   *
   * @param \Drupal\media_acquiadam\Entity\Asset $asset
   *   The asset to generate the URI.
   * @param \Drupal\media\MediaInterface $media
   *   The media entity for this asset.
   *
   * @return string
   *   The expected URI for the asset.
   *
   * @throws \Exception
   */
  protected function getAssetUri(Asset $asset, MediaInterface $media) {
    $destination_folder = $this->container
      ->get('media_acquiadam.asset_file.helper')
      ->getDestinationFromEntity($media, 'field_acquiadam_asset_file');

    return sprintf('%s/%s', $destination_folder, $asset->filename);
  }

}
