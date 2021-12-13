<?php

namespace Drupal\media_acquiadam\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media_acquiadam\Service\AssetImageHelper;
use Drupal\media_acquiadam\Service\AssetMediaFactory;
use Drupal\media_acquiadam\Service\AssetMetadataHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Acquia DAM assets.
 *
 * @MediaSource(
 *   id = "acquiadam_asset",
 *   label = @Translation("Acquia DAM asset"),
 *   description = @Translation("Provides business logic and metadata for
 *   assets stored on Acquia DAM."),
 *   allowed_field_types = {"string"},
 * )
 */
class AcquiadamAsset extends MediaSourceBase {

  /**
   * The asset that we're going to render details for.
   *
   * @var \Drupal\media_acquiadam\Entity\Asset
   */
  protected $currentAsset = NULL;

  /**
   * Acquia DAM asset image helper service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetImageHelper
   */
  protected $assetImageHelper;

  /**
   * Acquia DAM asset metadata helper service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetMetadataHelper
   */
  protected $assetMetadataHelper;

  /**
   * Acquia DAM Asset Media Factory service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetMediaFactory
   */
  protected $assetMediaFactory;

  /**
   * AcquiadamAsset constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, AssetImageHelper $assetImageHelper, AssetMetadataHelper $assetMetadataHelper, AssetMediaFactory $assetMediaFactory) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $entity_field_manager,
      $field_type_manager,
      $config_factory
    );

    $this->assetImageHelper = $assetImageHelper;
    $this->assetMetadataHelper = $assetMetadataHelper;
    $this->assetMediaFactory = $assetMediaFactory;

  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Fieldset with configuration options not needed.
    hide($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('media_acquiadam.asset_image.helper'),
      $container->get('media_acquiadam.asset_metadata.helper'),
      $container->get('media_acquiadam.asset_media.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => 'field_acquiadam_asset_id',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $submitted_config = array_intersect_key(
      $form_state->getValues(),
      $this->configuration
    );
    foreach ($submitted_config as $config_key => $config_value) {
      $this->configuration[$config_key] = $config_value;
    }

    // For consistency, always use the default source_field field name.
    $default_field_name = $this->defaultConfiguration()['source_field'];
    // Check if it already exists so it can be used as a shared field.
    $storage = $this->entityTypeManager->getStorage('field_storage_config');
    $existing_source_field = $storage->load('media.' . $default_field_name);

    // Set or create the source field.
    if ($existing_source_field) {
      // If the default field already exists, return the default field name.
      $this->configuration['source_field'] = $default_field_name;
    }
    else {
      // Default source field name does not exist, so create a new one.
      $field_storage = $this->createSourceFieldStorage();
      $field_storage->save();
      $this->configuration['source_field'] = $field_storage->getName();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createSourceFieldStorage() {
    $default_field_name = $this->defaultConfiguration()['source_field'];

    // Create the field.
    return $this->entityTypeManager->getStorage('field_storage_config')->create(
      [
        'entity_type' => 'media',
        'field_name' => $default_field_name,
        'type' => reset($this->pluginDefinition['allowed_field_types']),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return $this->assetMetadataHelper->getMetadataAttributeLabels();
  }

  /**
   * Gets the metadata for the given entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to get metadata from.
   * @param string $name
   *   The metadata item to get the value of.
   *
   * @return mixed|null
   *   The metadata value or NULL if unset.
   */
  public function getMetadata(MediaInterface $media, $name) {
    switch ($name) {
      case 'default_name':
        return parent::getMetadata($media, 'default_name');

      case 'thumbnail_uri':
        return $this->assetImageHelper->getThumbnail(
          $this->assetMediaFactory->get($media)->getFile()
        );

      case 'file':
        $file = $this->assetMediaFactory->get($media)->getFile();
        $is_file = !empty($file) && $file instanceof FileInterface;
        return $is_file ? $file->id() : NULL;
    }

    if (empty($this->currentAsset)) {
      $asset = $this->assetMediaFactory->get($media)->getAsset();
      if (empty($asset)) {
        return NULL;
      }
      $this->currentAsset = $asset;
    }

    return $this->assetMetadataHelper->getMetadataFromAsset(
      $this->currentAsset,
      $name
    );
  }

}
