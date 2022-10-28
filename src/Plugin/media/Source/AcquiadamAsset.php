<?php

namespace Drupal\media_acquiadam\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media_acquiadam\Service\AssetImageHelper;
use Drupal\media_acquiadam\Service\AssetMediaFactory;
use Drupal\media_acquiadam\Service\AssetMetadataHelper;
use GuzzleHttp\Exception\GuzzleException;
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
   * @var \Drupal\media_acquiadam\Entity\Asset|null
   */
  protected $currentAsset;

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
    /** @var \Drupal\field\FieldStorageConfigInterface $storage */
    return $this->entityTypeManager->getStorage('field_storage_config')
      ->create([
        'entity_type' => 'media',
        'field_name' => $default_field_name,
        'type' => reset($this->pluginDefinition['allowed_field_types']),
      ])->setIndexes([
        'value' => ['value'],
      ]);
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

    if ($this->currentAsset === NULL) {
      try {
        $asset = $this->assetMediaFactory->get($media)->getAsset();
        $this->currentAsset = $asset;
      }
      catch (GuzzleException $exception) {
        // Do nothing.
      }
    }

    // If we don't have the asset, we can't return additional metadata.
    if ($this->currentAsset === NULL) {
      return NULL;
    }
    $specificMetadataFields = $this->assetMetadataHelper->getSpecificMetadataFields();
    $value = $this->assetMetadataHelper->getMetadataFromAsset(
      $this->currentAsset,
      $name
    );
    if ($value === NULL) {
      return $value;
    }

    // The field mapping is used by some attributes to transform values for
    // better storage compatibility.
    $field_map = $media->bundle->entity->getFieldMap();
    $field_definition = NULL;
    if (isset($field_map[$name])) {
      $field_definition = $media->getFieldDefinition($field_map[$name]);
    }

    $datetime_properties = [
      'created_date',
      'last_update_date',
      'file_upload_date',
      'deleted_date',
      'expiration_date',
      'release_date',
    ];
    if (in_array($name, $datetime_properties, TRUE)) {
      $value = $this->transformMetadataForStorage($value,'datetime', $field_definition);
    }
    elseif (isset($specificMetadataFields[$name])) {
      $type = $specificMetadataFields[$name]['type'];
      // The v1 API reports date metadata types as `datetime`, but they are
      // only dates.
      if ($type === 'datetime') {
        $value = $this->transformMetadataForStorage($value,'date', $field_definition);
      }
    }
    return $value;
  }

  /**
   * Transforms metadata values for field storage.
   *
   * @param string|array $value
   *   The metadata's value.
   * @param string $metadata_type
   *   The metadata's type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface|null $field_definition
   *   The field definition, if metadata is mapped to a field.
   *
   * @return string|array
   *   The transformed metadata values.
   */
  private function transformMetadataForStorage($value, string $metadata_type, ?FieldDefinitionInterface $field_definition) {
    if ($field_definition === NULL) {
      return $value;
    }
    $field_type = $field_definition->getType();
    $field_storage_definition = $field_definition->getFieldStorageDefinition();

    if (in_array($metadata_type, ['date', 'datetime'])) {
      $source_format = $metadata_type === 'date' ? 'Y-m-d' : \DateTimeInterface::ATOM;
      if ($field_type === 'datetime') {
        $datetime_type = $field_storage_definition->getSetting('datetime_type');
        $format = $datetime_type === DateTimeItem::DATETIME_TYPE_DATETIME ? DateTimeItemInterface::DATETIME_STORAGE_FORMAT : DateTimeItemInterface::DATE_STORAGE_FORMAT;
      }
      elseif ($field_type === 'timestamp') {
        $format = 'U';
      }
      else {
        return $value;
      }

      if (is_array($value)) {
        $value = array_map(function ($value) use ($source_format, $format) {
          return $this->formatDateForDateField($value, $source_format, $format);
        }, $value);
      }
      else {
        $value = $this->formatDateForDateField($value, $source_format, $format);
      }
    }

    return $value;
  }

  /**
   * Formats date coming from DAM to save into storage.
   *
   * @param string $value
   *   Date string coming from API in ISO8601 format.
   * @param string $source_format
   *   The source date time format.
   * @param string $format
   *   The date time format.
   *
   * @return string
   *  The formatted date.
   */
  private function formatDateForDateField(string $value, string $source_format, string $format): string {
    try {
      $date = DrupalDateTime::createFromFormat(
        $source_format,
        $value,
        'UTC',
        [
          // We do not want to validate the format. Incoming ISO8601 has the Z
          // timezone offset, while PHP may return +00:00 when comparing the
          // output with the `P` option.
          'validate_format' => FALSE,
        ]
      );
      // If the format did not include an explicit time portion, then the time
      // will be set from the current time instead. Provide a default for
      // consistent values.
      if (!str_contains($value, 'T')) {
        $date->setDefaultDateTime();
      }
    }
    catch (\InvalidArgumentException | \UnexpectedValueException $exception) {
      return $value;
    }
    return $date->format($format);
  }

}
