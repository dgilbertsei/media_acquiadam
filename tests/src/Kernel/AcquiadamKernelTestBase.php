<?php

namespace Drupal\Tests\media_acquiadam\Kernel;

use cweagans\webdam\Entity\Asset;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media_acquiadam\ClientFactory;
use Drupal\media_acquiadam_test\TestClient;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;

/**
 * Base class for Acquia DAM kernel tests.
 */
abstract class AcquiadamKernelTestBase extends EntityKernelTestBase {

  use AcquiadamAssetDataTrait;

  const DEFAULT_BUNDLE = 'acquia_dam_asset';
  /**
   * Set to FALSE so it doesn't strict check all configuration saved.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

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
   * {@inheritdoc}
   */
  protected function setUp() {

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

    $acquiadam_client_factory = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $acquiadam_client_factory->expects($this->any())
      ->method('get')
      ->willReturn($this->testClient);

    $this->container->set('media_acquiadam.client_factory',
      $acquiadam_client_factory);

    \Drupal::setContainer($this->container);
  }

  /**
   * Creates a media entity with a given Asset ID.
   *
   * @param int $asset_id
   *   The asset ID.
   * @param string $bundle
   *   The media entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\media\Entity\Media
   *   The created media entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createMedia(int $asset_id, string $bundle = self::DEFAULT_BUNDLE) {
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
   * @param \cweagans\webdam\Entity\Asset $asset
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
    $destinationFolder = $this->container
      ->get('media_acquiadam.asset_file.helper')
      ->getDestinationFromEntity($media, 'field_acquiadam_asset_file');

    return sprintf('%s/%s', $destinationFolder, $asset->filename);
  }

}
