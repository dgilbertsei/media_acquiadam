<?php

namespace Drupal\Tests\media_acquiadam\Kernel;

use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset;

/**
 * Tests Media entities with Acquia DAM source.
 *
 * @group media_acquiadam
 */
class AcquiadamMediaTest extends AcquiadamKernelTestBase {

  /**
   * The initial asset for this test.
   *
   * @var \Drupal\media_acquiadam\Entity\Asset
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
   * Reflection class so we can update cached assets.
   *
   * @var \ReflectionClass
   */
  protected $acquiadamReflectionClass;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sourceReflectionClass = new \ReflectionClass(AcquiadamAsset::class);
    $this->acquiadamReflectionClass = new \ReflectionClass(Acquiadam::class);

    $this->asset = $this->getAssetData();

    // Create file with same name as asset file to make sure asset file
    // replacement happens as expected.
    $dir_path = 'public://acquiadam/';
    $contents = 'test';
    $this->container->get('file_system')->prepareDirectory($dir_path, FileSystemInterface::CREATE_DIRECTORY);
    file_save_data($contents, $dir_path . $this->asset->filename);

    $this->testClient->addAsset($this->asset);
    $this->media = $this->createMedia($this->asset->id);
  }

  /**
   * Tests if field mappings work as expected.
   */
  public function testFieldMappings() {
    $media_file_uri = $this->getAssetFileEntity($this->media)->getFileUri();
    $expected_asset_uri = $this->getAssetUri($this->asset, $this->media);

    $this->assertEquals($this->media->label(), $this->asset->filename, 'Media name mapped to asset filename as expected.');
    $this->assertEquals($media_file_uri, $expected_asset_uri, 'Media file URI mapped as expected.');
  }

  /**
   * Tests updating media entity when new version is available.
   */
  public function testNewVersionUpdate() {

    $this->saveNewVersion();

    $file = $this->getAssetFileEntity($this->media);
    $file_uri = $file->getFileUri();
    $expected_asset_uri = $this->getAssetUri($this->asset, $this->media);

    $this->assertEquals($this->media->label(), $this->asset->filename, 'Media name updated as expected.');
    $this->assertEquals($file_uri, $expected_asset_uri, 'Media asset file updated as expected.');
    $this->assertEquals($file->label(), $this->asset->filename, 'File entity label updated as expected.');
  }

  /**
   * Tests that version is only updated when file is saved correctly.
   */
  public function testFailedFileSave() {
    /** @var \Drupal\media_acquiadam\Service\AssetFileEntityHelper $asset_file_helper */
    $asset_file_helper = $this->container->get('media_acquiadam.asset_file.helper');
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = $this->container->get('file_system');

    // Makes directory read only so file save fails.
    $directory = $asset_file_helper->getDestinationFromEntity($this->media, 'field_acquiadam_asset_file');
    $file_system->chmod($directory, 0000);

    // Attempts to save new version of asset while directory isn't accessible.
    $this->saveNewVersion();
    $new_version = $this->acquiaAssetData->isUpdatedAsset($this->asset);

    $this->assertEquals(TRUE, $new_version, 'Asset version unchanged as expected.');

    // Restore permissions to directory and resave entity.
    $file_system->chmod($directory, FileSystem::CHMOD_DIRECTORY);
    $this->reSaveMedia();
    $new_version = $this->acquiaAssetData->isUpdatedAsset($this->asset);

    $this->assertNotEquals(FALSE, $new_version, 'New version different from old version.');
    $this->assertEquals(TRUE, $new_version, 'Asset version updated as expected.');
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
      'id' => '34asd3q2-e294-4908-bbd9-f43f433d2e33',
      'filename' => 'other_file.jpg',
    ]);
    $this->testClient->addAsset($other_asset);
    $other_media = $this->createMedia($other_asset->id);
    $other_file = $this->getAssetFileEntity($other_media);

    // Create a new version for intial asset and re-save corresponding media
    // entity to test if file was updated correctly.
    $this->saveNewVersion();

    // Re-loads FID to assert it's unchanged.
    $actual_fid = $this->getAssetFileEntity($this->media)->id();
    $this->assertEquals($actual_fid, $expected_fid, 'First media entity still has reference to the expected file.');

    // Asserts second media file is still correct.
    $this->assertEquals($other_file->getFileUri(), $this->getAssetUri($other_asset, $other_media), 'Second media entity still has the expected URI.');
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
   * Generates a new version of the asset and resaves media entity.
   */
  protected function saveNewVersion() {
    $this->asset = $this->generateNewVersion($this->asset);
    $this->testClient->addAsset($this->asset);
    $this->reSaveMedia();
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

    $cached_assets_property = $this->acquiadamReflectionClass->getProperty('cachedAssets');
    $cached_assets_property->setAccessible(TRUE);
    $cached_assets_property->setValue([]);
  }

}
