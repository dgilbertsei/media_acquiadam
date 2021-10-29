<?php

namespace Drupal\media_acquiadam\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\media_acquiadam\Entity\Asset;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetMetadataHelper.
 *
 * Deals with reading and manipulating metadata for assets.
 */
class AssetMetadataHelper implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Drupal date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * A configured API object.
   *
   * @var \Drupal\media_acquiadam\AcquiadamInterface|\Drupal\media_acquiadam\Client
   *   $acquiadam
   */
  protected $acquiadam;

  /**
   * Specific metadata fields.
   *
   * @var array
   */
  protected $specificMetadataFields = [];

  /**
   * AssetImageHelper constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   A Drupal date formatter service.
   * @param \Drupal\media_acquiadam\AcquiadamInterface|\Drupal\media_acquiadam\Client $acquiadam
   *   A configured API object.
   */
  public function __construct(DateFormatterInterface $dateFormatter, AcquiadamInterface $acquiadam) {
    $this->dateFormatter = $dateFormatter;
    $this->acquiadam = $acquiadam;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('media_acquiadam.acquiadam')
    );
  }

  /**
   * Set the available specific metadata fields.
   *
   * <code>
   * [
   *   'assettype' => [
   *     'label' => 'Asset type',
   *     'type' => 'string',
   *   ],
   *   'author' => [
   *     'label' => 'Author',
   *     'type' => 'string',
   *   ]
   * ]
   * </code>
   *
   * @param array $fields
   *   Fields contains an array.
   */
  public function setSpecificMetadataFields(array $fields = []) {
    $this->specificMetadataFields = $fields;
  }

  /**
   * Get the available specific metadata fields.
   *
   * @return array
   *   An array contain specific metadata fields.
   */
  public function getSpecificMetadataFields() {
    if (empty($this->specificMetadataFields)) {
      $this->setSpecificMetadataFields(
        $this->acquiadam->getSpecificMetadataFields()
      );
    }

    return $this->specificMetadataFields;
  }

  /**
   * Get the available metadata attribute labels.
   *
   * @return array
   *   An array of possible metadata attributes keyed by their ID.
   */
  public function getMetadataAttributeLabels() {
    $fields = [
      'external_id' => $this->t('External ID'),
      'filename' => $this->t('Filename'),
      'created_date' => $this->t('Created date'),
      'last_update_date' => $this->t('Last update date'),
      'file_upload_date' => $this->t('File upload date'),
      'deleted_date' => $this->t('Deleted date'),
      'released_and_not_expired' => $this->t('Released and not expired'),
      'expiration_date' => $this->t('Expiration date'),
      'release_date' => $this->t('Release date'),
      'format' => $this->t('Format'),
      'file' => $this->t('File'),
      'size_in_kbytes' => $this->t('Filesize (kb)'),
      'height' => $this->t('Height'),
      'width' => $this->t('Width'),
      'popularity' => $this->t('Popularity'),
      'duration' => $this->t('Duration'),
    ];

    // Add specific metadata fields to fields array.
    $specificMetadataFields = $this->getSpecificMetadataFields();
    if (!empty($specificMetadataFields)) {
      foreach ($specificMetadataFields as $id => $field) {
        $fields[$id] = $field['label'];
      }
    }

    return $fields;
  }

  /**
   * Gets a metadata item from the given asset.
   *
   * @param \Drupal\media_acquiadam\Entity\Asset $asset
   *   The asset to get metadata from.
   * @param string $name
   *   The name of the metadata item to retrieve.
   *
   * @return mixed
   *   Result will vary based on the metadata item.
   */
  public function getMetadataFromAsset(Asset $asset, $name) {
    $specificMetadataFields = $this->getSpecificMetadataFields();
    if (array_key_exists($name, $specificMetadataFields)) {
      if (is_array($asset->metadata->fields->{$name}) && !empty($asset->metadata->fields->{$name})) {
        return reset($asset->metadata->fields->{$name});
      }

      return !empty($asset->metadata->fields->{$name}) ? $asset->metadata->fields->{$name} : NULL;
    }

    // Some properties are available either in image_properties or
    // video_properties depending the asset type.
    $additional_properties = isset($asset->file_properties->image_properties) ? 'image_properties' : 'video_properties';

    switch ($name) {
      case 'expiration_date':
      case 'release_date':
        return $asset->security->{$name} ?? NULL;

      case 'popularity':
        return $asset->asset_properties->popularity ?? NULL;

      case 'size_in_kbytes':
        return $asset->file_properties->{$name} ?? NULL;

      case 'height':
      case 'width':
      case 'duration':
        return $asset->file_properties->{$additional_properties}->{$name} ?? NULL;

      default:
        // The key should be the local property name and the value should be the
        // DAM provided property name.
        $property_name_mapping = [
          'external_id' => 'external_id',
          'filename' => 'filename',
          'created_date' => 'created_date',
          'last_update_date' => 'last_update_date',
          'file_upload_date' => 'file_upload_date',
          'deleted_date' => 'deleted_date',
          'released_and_not_expired' => 'released_and_not_expired',
        ];
        if (array_key_exists($name, $property_name_mapping)) {
          $property_name = $property_name_mapping[$name];
          return $asset->{$property_name} ?? NULL;
        }
    }

    return NULL;
  }

}
