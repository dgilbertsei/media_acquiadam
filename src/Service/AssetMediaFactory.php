<?php

namespace Drupal\media_acquiadam\Service;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\media_acquiadam\MediaEntityHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetMediaFactory.
 *
 * Factory service to get wrapped Media entities that provide additional asset
 * functionality.
 */
class AssetMediaFactory implements ContainerInjectionInterface {

  /**
   * Drupal entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity storage for Media types.
   *
   * @var \Drupal\media\MediaStorage
   */
  protected $mediaStorage;

  /**
   * The class name to use when returning a wrapped media entity.
   *
   * @var string
   */
  protected $mediaEntityHelperClass;

  /**
   * AssetMediaFactory constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->mediaStorage = $this->entityTypeManager->getStorage('media');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Set the class name to use when wrapping media entities.
   *
   * @param string $className
   *   The fully namespaced class name to use.
   */
  public function setAssetMediaEntityHelperClass($className = MediaEntityHelper::class) {
    $this->mediaEntityHelperClass = $className;
  }

  /**
   * Get the media source an asset belongs to.
   *
   * @param int $assetId
   *   The ID of the asset to get the media source for.
   * @param string $bundle
   *   A specific bundle to fetch the media entity from.
   *
   * @return false|\Drupal\media\MediaSourceInterface
   *   The media source for the given asset or FALSE.
   */
  public function getMediaSource($assetId, $bundle = NULL) {
    $media = $this->getMediaEntity($assetId, $bundle);
    return $media instanceof MediaInterface ? $media->getSource() : FALSE;
  }

  /**
   * Get an existing media entity for the given asset.
   *
   * @param int $assetId
   *   The ID of the asset to get the media entity for.
   * @param string $bundle
   *   A specific bundle to fetch the media entity from.
   *
   * @return \Drupal\media\MediaInterface|false
   *   The media entity the given asset is associated with or FALSE.
   *
   * @BUG: Only returns the first media entity it finds.
   * It's technically possible for assets to belong to different media entities.
   */
  public function getMediaEntity($assetId, $bundle = NULL) {
    $usage = $this->getAssetUsage($assetId, $bundle);
    if (empty($usage)) {
      return FALSE;
    }

    $item = reset($usage);
    return !empty($item[0]) ? $this->mediaStorage->load($item[0]) : FALSE;
  }

  /**
   * Get all media entities an asset belongs to.
   *
   * @param int $assetId
   *   The ID of the asset to get the media entities for.
   * @param string $bundle
   *   A specific bundle to fetch the media entity from.
   *
   * @return \Drupal\media\MediaInterface[]|false
   *   An array of media entities keyed by their bundles or FALSE on failure.
   */
  public function getMediaEntities($assetId, $bundle = NULL) {
    $usage = $this->getAssetUsage($assetId, $bundle);
    if (empty($usage)) {
      return FALSE;
    }

    foreach ($usage as $bundle => $entity_ids) {
      $usage[$bundle] = $this->mediaStorage->loadMultiple($entity_ids);
    }

    return $usage;
  }

  /**
   * Get a collection of media entities using the given asset ID.
   *
   * @param int $assetId
   *   The asset ID to check usages of.
   * @param string $bundle
   *   A media bundle to filter usage information by.
   *
   * @return array
   *   An array of media IDs using the given asset ID, keyed by their bundle.
   */
  public function getAssetUsage($assetId, $bundle = NULL) {
    $asset_id_fields = $this->getAssetIdFields();
    if (empty($asset_id_fields)) {
      return [];
    }

    $usages = [];

    foreach ($asset_id_fields as $key => $field) {
      if (!empty($bundle) && $key != $bundle) {
        continue;
      }

      $media_ids = $this->getMediaBundleFields($key, $field, $assetId);
      if (!empty($media_ids)) {
        $usages[$key] = array_values($media_ids);
      }
    }

    return $usages;
  }

  /**
   * Gets a list of media IDs by bundle.
   *
   * Primarily broken out to make test mocking easier.
   *
   * @param string $bundle
   *   The media bundle to filter by.
   * @param string $field
   *   The asset ID field.
   * @param int $assetId
   *   The asset ID to get media entity IDs for.
   *
   * @return array|int
   *   A list of media entity IDs.
   */
  protected function getMediaBundleFields($bundle, $field, $assetId) {
    return $this->mediaStorage->getQuery()
      ->condition('bundle', $bundle)
      ->condition($field, $assetId)
      ->execute();
  }

  /**
   * Get a list of asset ID fields related to their bundle.
   *
   * @return array
   *   An array of media bundles and associated asset ID fields
   */
  public function getAssetIdFields() {
    // @todo Static caching.
    try {
      $media_bundles = $this->entityTypeManager
        ->getStorage('media_type')
        ->loadByProperties(['source' => 'acquiadam_asset']);
    }
    catch (\Exception $x) {
      return [];
    }

    $asset_id_fields = [];
    /** @var \Drupal\media\Entity\MediaType $bundle */
    foreach ($media_bundles as $name => $bundle) {
      $asset_id_fields[$name] = $bundle->getSource()
        ->getSourceFieldDefinition($bundle)
        ->getName();
    }

    return $asset_id_fields;
  }

  /**
   * Get an existing file entity for the given asset.
   *
   * @param int $assetId
   *   The ID of the asset to load the file for.
   * @param string $bundle
   *   A specific bundle to fetch the media entity from.
   *
   * @return \Drupal\file\FileInterface|false
   *   An existing file entity for the asset or FALSE.
   */
  public function getFileEntity($assetId, $bundle = NULL) {
    $media = $this->getMediaEntity($assetId, $bundle);
    return $media instanceof MediaInterface ?
      $this->get($media)->getExistingFile() : FALSE;
  }

  /**
   * Wrap a media entity with a helper to enable asset functionality.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to wrap.
   *
   * @return \Drupal\media_acquiadam\MediaEntityHelper
   *   A media entity wrapped with a helper class.
   */
  public function get(MediaInterface $media) {
    // Not ideal but the usage of media_acquiadam.asset_file.helper gets flagged
    // as a circular dependency even though it's just a passthrough to another
    // class.
    // phpcs:disable DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $container = \Drupal::getContainer();
    // phpcs:enable

    $helper_class = $this->getAssetMediaEntityHelperClass();
    return new $helper_class($media,
      $this->entityTypeManager,
      $container->get('media_acquiadam.asset_data'),
      $container->get('media_acquiadam.acquiadam'),
      $container->get('media_acquiadam.asset_file.helper'));
  }

  /**
   * Get the media entity helper class name.
   *
   * @return string
   *   The name of the class to use when wrapping media entities.
   */
  public function getAssetMediaEntityHelperClass() {
    if (!empty($this->mediaEntityHelperClass) && class_exists($this->mediaEntityHelperClass)) {
      return $this->mediaEntityHelperClass;
    }
    return MediaEntityHelper::class;
  }

}
