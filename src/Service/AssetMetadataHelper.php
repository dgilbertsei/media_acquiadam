<?php

namespace Drupal\acquiadam\Service;

use Drupal\acquiadam\Entity\Asset;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\acquiadam\AcquiadamInterface;
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
   * @var \Drupal\acquiadam\AcquiadamInterface|\Drupal\acquiadam\Client
   *   $acquiadam
   */
  protected $acquiadam;

  /**
   * Array of DAM XMP fields keyed by field (prefixed with "xmp_").
   *
   * @var array
   */
  protected $xmpMetadataFields = NULL;

  /**
   * AssetImageHelper constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   A Drupal date formatter service.
   * @param \Drupal\acquiadam\AcquiadamInterface|\Drupal\acquiadam\Client $acquiadam
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
      $container->get('acquiadam.acquiadam')
    );
  }

  /**
   * Set the available XMP metadata fields.
   *
   * <code>
   * [
   *   'xmp_caption' => [
   *     'name' => 'Caption/Abstract',
   *     'label' => 'Caption/Description',
   *     'type' => 'textarea',
   *   ],
   *   'xmp_byline' => [
   *     'name' => 'By-line',
   *     'label' => 'Photographer',
   *     'type' => 'text',
   *   ],
   * ]
   * </code>
   *
   * @param array $fields
   *   Sets the available XMP metadata fields.
   */
  public function setMetadataXmpFields(array $fields = []) {
    $this->xmpMetadataFields = $fields;
  }

  /**
   * Get the available XMP metadata fields.
   *
   * Also check if the xmpMetadatafiels are not set call the acquiadam.
   *
   * @return array
   *   The xmpMetadataFields array.
   */
  public function getMetadataXmpFields() {
    if (is_null($this->xmpMetadataFields)) {
      $this->setMetadataXmpFields(
        $this->acquiadam->getActiveXmpFields()
      );
    }
    return $this->xmpMetadataFields;
  }

  /**
   * Get the available metadata attribute labels.
   *
   * @return array
   *   An array of possible metadata attributes keyed by their ID.
   */
  public function getMetadataAttributeLabels() {
    $fields = [
      'colorspace' => $this->t('Color space'),
      'datecaptured' => $this->t('Date captured'),
      'datecreated' => $this->t('Date created'),
      'datemodified' => $this->t('Date modified'),
      'datecaptured_date' => $this->t('Date captured (Date)'),
      'datecreated_date' => $this->t('Date created (Date)'),
      'datemodified_date' => $this->t('Date modified (Date)'),
      'datecaptured_unix' => $this->t('Date captured (Timestamp)'),
      'datecreated_unix' => $this->t('Date created (Timestamp)'),
      'datemodified_unix' => $this->t('Date modified (Timestamp)'),
      'description' => $this->t('Description'),
      'file' => $this->t('File'),
      'filename' => $this->t('Filename'),
      'filesize' => $this->t('Filesize'),
      'filetype' => $this->t('Filetype'),
      'folderID' => $this->t('Folder ID'),
      'height' => $this->t('Height'),
      'status' => $this->t('Active state'),
      'type' => $this->t('Type'),
      'type_id' => $this->t('Type ID'),
      'id' => $this->t('Asset ID'),
      'version' => $this->t('Version'),
      'width' => $this->t('Width'),
    ];

    // Add additional XMP fields to fields array.
    $xmpMetadataFields = $this->getMetadataXmpFields();
    if (!empty($xmpMetadataFields)) {
      foreach ($xmpMetadataFields as $xmp_id => $xmp_field) {
        $fields[$xmp_id] = $xmp_field['label'];
      }
    }

    return $fields;
  }

  /**
   * Gets a metadata item from the given asset.
   *
   * @param \Drupal\acquiadam\Entity\Asset $asset
   *   The asset to get metadata from.
   * @param string $name
   *   The name of the metadata item to retrieve.
   *
   * @return mixed
   *   Result will vary based on the metadata item.
   */
  public function getMetadataFromAsset(Asset $asset, $name) {

    // Return values of XMP metadata.
    $xmpMetadataFields = $this->getMetadataXmpFields();
    if (array_key_exists($name, $xmpMetadataFields)) {
      // Strip 'xmp_' prefix to retrieve matching asset xmp metadata.
      $xmp_field = substr($name, 4);

      return isset($asset->xmp_metadata[$xmp_field]['value']) ?
        $asset->xmp_metadata[$xmp_field]['value'] : NULL;
    }

    switch ($name) {
      case 'folderID':
        return isset($asset->folder->id) ? $asset->folder->id : NULL;

      case 'status':
        return isset($asset->status) ? intval($asset->status == 'active') :
          NULL;

      case 'type':
        if (isset($asset->type_id)) {
          $type_mapping = [
            1 => 'Image',
            2 => 'Video',
            3 => 'Document',
            4 => 'Presentation',
            5 => 'Other',
          ];

          return $type_mapping[$asset->type_id] ?: NULL;
        }
        return NULL;

      case 'datecaptured_date':
      case 'datecreated_date':
      case 'datemodified_date':
        $date_property_mapping = [
          'datecaptured_date' => 'datecapturedUnix',
          'datecreated_date' => 'date_created_unix',
          'datemodified_date' => 'date_modified_unix',
        ];
        $date_property = $date_property_mapping[$name];
        if (!empty($asset->{$date_property})) {
          // html_datetime includes the timezone so we must use a custom format.
          return $this->dateFormatter->format(
            $asset->{$date_property},
            'custom',
            'Y-m-d\TH:i:s'
          );
        }
        return NULL;

      default:
        // The key should be the local property name and the value should be the
        // DAM provided property name.
        $property_name_mapping = [
          'colorspace' => 'colorspace',
          'datecaptured' => 'datecaptured',
          'datecreated' => 'datecreated',
          'datemodified' => 'datemodified',
          'datecaptured_unix' => 'datecapturedUnix',
          'datecreated_unix' => 'date_created_unix',
          'datemodified_unix' => 'date_modified_unix',
          'description' => 'description',
          'filename' => 'filename',
          'filesize' => 'filesize',
          'filetype' => 'filetype',
          'height' => 'height',
          'id' => 'id',
          'type_id' => 'type_id',
          'version' => 'version',
          'width' => 'width',
        ];
        if (array_key_exists($name, $property_name_mapping)) {
          $property_name = $property_name_mapping[$name];
          return isset($asset->{$property_name}) ? $asset->{$property_name} :
            NULL;
        }
    }

    return NULL;
  }

}
