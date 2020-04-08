<?php

namespace Drupal\Tests\media_acquiadam\Kernel;

use Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset;

/**
 * Tests Media entities with Media: Acquia DAM source.
 *
 * @group media_acquiadam
 */
class AcquiadamMediaTest extends AcquiadamKernelTestBase {

  /**
   * The initial asset for this test.
   *
   * @var \cweagans\webdam\Entity\Asset
   */
  protected $asset;

  /**
   * The media entity with mocked asset data.
   *
   * @var \Drupal\media\Entity\Media
   */
  protected $media;

  /**
   * Reflection class so we can update properties from the Media source.
   *
   * @var \ReflectionClass
   */
  protected $sourceReflectionClass;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->sourceReflectionClass = new \ReflectionClass(AcquiadamAsset::class);

    $this->asset = $this->getAssetData();
    $this->testClient->addAsset($this->asset);
    $this->media = $this->createMedia($this->asset->id);
  }

  /**
   * Tests if field mappings work as expected.
   */
  public function testFieldMappings() {
    $media_description = $this->media->get('field_acquiadam_asset_descrip')->getString();
    $media_file_uri = $this->getAssetFileEntity($this->media)->getFileUri();
    $expected_asset_uri = $this->getAssetUri($this->asset, $this->media);

    $this->assertEqual($this->media->label(), $this->asset->filename, 'Media name mapped to asset filename as expected.');
    $this->assertEqual($media_description, $this->asset->description, 'Media description mapped to asset description as expected.');
    $this->assertEqual($media_file_uri, $expected_asset_uri, 'Media file URI mapped as expected.');
  }

  /**
   * Tests updating media entity when new version is available.
   */
  public function testNewVersionUpdate() {
    $this->asset = $this->generateNewVersion($this->asset);
    $this->testClient->addAsset($this->asset);
    $this->reSaveMedia();

    $file = $this->getAssetFileEntity($this->media);
    $file_uri = $file->getFileUri();
    $expected_asset_uri = $this->getAssetUri($this->asset, $this->media);

    $this->assertEqual($this->media->label(), $this->asset->filename, 'Media name updated as expected.');
    $this->assertEqual($file_uri, $expected_asset_uri, 'Media asset file updated as expected.');
    $this->assertEqual($file->label(), $this->asset->filename, 'File entity label updated as expected.');
  }

  /**
   * Tests if updating multiple revisionable entities.
   *
   * See DAM-157 for context.
   */
  public function testAssetFileIsCorrect() {

    // Store the unchanged FID and create a new revision.
    $expected_fid = $this->getAssetFileEntity($this->media)->id();
    $this->createNewMediaRevision();

    // Create other media entity to test if its asset file won't be referenced
    // by first media entity.
    $other_asset = $this->getAssetData([
      'id' => 3455970,
      'filename' => 'other_file.jpg',
    ]);
    $this->testClient->addAsset($other_asset);
    $other_media = $this->createMedia($other_asset->id);
    $other_file = $this->getAssetFileEntity($other_media);

    // Create a new version for intial asset and re-save corresponding media
    // entity to test if file was updated correctly.
    $this->asset = $this->generateNewVersion($this->asset);
    $this->testClient->addAsset($this->asset);
    $this->reSaveMedia();

    // Re-loads FID to assert it's unchanged.
    $actual_fid = $this->getAssetFileEntity($this->media)->id();
    $this->assertEqual($actual_fid, $expected_fid, 'First media entity still has reference to the expected file.');

    // Asserts second media file is still correct.
    $this->assertEqual($other_file->getFileUri(), $this->getAssetUri($other_asset, $other_media), 'Second media entity still has the expected URI.');
  }

  /**
   * Re-saves media to generate new revision.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createNewMediaRevision() {
    $this->media->setName('test');
    $this->media->setNewRevision(TRUE);
    $this->media->save();
  }

  /**
   * Re-saves the media to get new updates.
   */
  protected function reSaveMedia() {

    // Clear current asset so we get new updates from assets.
    $this->clearCurrentAssetFromSource($this->media->getSource());

    // Saves a new revision for this entity.
    $this->media->setNewRevision(TRUE);
    $this->media->save();
  }

  /**
   * Clears source current asset so we can simulate updates from the API.
   *
   * @param \Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset $source
   *   The source to clear.
   */
  protected function clearCurrentAssetFromSource(AcquiadamAsset $source) {
    $current_asset_property = $this->sourceReflectionClass->getProperty('currentAsset');
    $current_asset_property->setAccessible(TRUE);
    $current_asset_property->setValue($source, NULL);

    $acquiadam_property = $this->sourceReflectionClass->getProperty('acquiadam');
    $acquiadam_property->setAccessible(TRUE);
    /** @var \Drupal\media_acquiadam\Acquiadam $acquiadam */
    $acquiadam = $acquiadam_property->getValue($source);
    $acquiadam->staticAssetCache('clear');
  }

}
