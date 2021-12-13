<?php

namespace Drupal\media_acquiadam;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\media_acquiadam\Service\AssetFileEntityHelper;

/**
 * Class MediaEntityHelper.
 *
 * Functionality related to working with the Media entity that assets are tied
 * to. The intent is to make it easier to test and rework the behavior without
 * having everything in a singular class.
 */
class MediaEntityHelper {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Acquia DAM asset data service.
   *
   * @var \Drupal\media_acquiadam\AssetData
   */
  protected $assetData;

  /**
   * Acquia DAM client.
   *
   * @var \Drupal\media_acquiadam\Acquiadam
   */
  protected $acquiaDamClient;

  /**
   * Acquia DAM asset file helper service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetFileEntityHelper
   */
  protected $assetFileHelper;

  /**
   * The media entity that is being wrapped.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $mediaEntity;

  /**
   * MediaEntityHelper constructor.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to wrap.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\media_acquiadam\AssetDataInterface $assetData
   *   Acquia DAM asset data service.
   * @param \Drupal\media_acquiadam\AcquiadamInterface $acquiaDamClient
   *   Acquia DAM client.
   * @param \Drupal\media_acquiadam\Service\AssetFileEntityHelper $assetFileHelper
   *   Acquia DAM file entity helper service.
   */
  public function __construct(MediaInterface $media, EntityTypeManagerInterface $entityTypeManager, AssetDataInterface $assetData, AcquiadamInterface $acquiaDamClient, AssetFileEntityHelper $assetFileHelper) {
    $this->entityTypeManager = $entityTypeManager;
    $this->assetData = $assetData;
    $this->acquiaDamClient = $acquiaDamClient;
    $this->assetFileHelper = $assetFileHelper;

    $this->mediaEntity = $media;
  }

  /**
   * Returns an associated file or creates a new one.
   *
   * @return false|\Drupal\file\FileInterface
   *   A file entity or FALSE on failure.
   */
  public function getFile() {
    // If there is already a file on the media entity then we should use that.
    $file = $this->getExistingFile();

    // If we're getting an updated version of the asset we need to grab a new
    // version of the file.
    $asset = $this->getAsset();
    if (!empty($asset)) {
      $is_different_version = $this->assetData->isUpdatedAsset($asset);

      if (empty($file) || $is_different_version) {
        $destination_folder = $this->getAssetFileDestination();
        $file = $this->assetFileHelper->createNewFile($asset, $destination_folder);

        if ($file) {
          $this->assetData->set($asset->id, 'file_upload_date', strtotime($asset->file_upload_date));
        }
      }
    }

    return $file;
  }

  /**
   * Attempts to load an existing file entity from the given media entity.
   *
   * @return \Drupal\file\FileInterface|false
   *   A loaded file entity or FALSE if none could be found.
   */
  public function getExistingFile() {
    try {
      if ($fid = $this->getExistingFileId()) {
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entityTypeManager->getStorage('file')->load($fid);
      }
    }
    catch (\Exception $x) {
      $file = FALSE;
    }

    return !empty($file) ? $file : FALSE;
  }

  /**
   * Gets the existing file ID from the given Media entity.
   *
   * @return int|false
   *   The existing file ID or FALSE if one was not found.
   */
  public function getExistingFileId() {
    return $this->getFieldPropertyValue($this->getAssetFileField()) ?? FALSE;
  }

  /**
   * Gets the file field being used to store the asset.
   *
   * @return false|string
   *   The name of the file field on the media bundle or FALSE on failure.
   */
  public function getAssetFileField() {
    try {
      /** @var \Drupal\media\Entity\MediaType $bundle */
      $bundle = $this->entityTypeManager->getStorage('media_type')
        ->load($this->mediaEntity->bundle());
      $field_map = !empty($bundle) ? $bundle->getFieldMap() : FALSE;
    }
    catch (\Exception $x) {
      return FALSE;
    }

    return empty($field_map['file']) ? FALSE : $field_map['file'];
  }

  /**
   * Get the asset from a media entity.
   *
   * @return bool|\Drupal\media_acquiadam\Entity\Asset
   *   The asset or FALSE on failure.
   */
  public function getAsset() {
    $assetId = $this->getAssetId();
    return !empty($assetId) ? $this->acquiaDamClient->getAsset($assetId) :
      FALSE;
  }

  /**
   * Get the asset ID for the given media entity.
   *
   * @return string|false
   *   The asset ID or FALSE on failure.
   */
  public function getAssetId() {
    $sourceField = $this->mediaEntity->getSource()
      ->getSourceFieldDefinition($this->mediaEntity->get('bundle')->entity)
      ->getName();
    return $this->getFieldPropertyValue($sourceField) ?? FALSE;
  }

  /**
   * Gets the destination path for Acquia DAM assets.
   *
   * @return string
   *   The final folder to store the asset locally.
   */
  public function getAssetFileDestination() {
    return $this->assetFileHelper->getDestinationFromEntity($this->mediaEntity,
      $this->getAssetFileField());
  }

  /**
   * Gets the value of a field without knowing the key to use.
   *
   * @param string $fieldName
   *   The field name.
   *
   * @return null|mixed
   *   The field value or NULL.
   */
  protected function getFieldPropertyValue($fieldName) {
    if ($this->mediaEntity->hasField($fieldName)) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      $item = $this->mediaEntity->{$fieldName}->first();
      if (!empty($item)) {
        $property_name = $item->mainPropertyName();
        if (isset($this->mediaEntity->{$fieldName}->{$property_name})) {
          return $this->mediaEntity->{$fieldName}->{$property_name};
        }
      }
    }

    return NULL;
  }

}
