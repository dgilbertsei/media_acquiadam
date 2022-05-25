<?php

namespace Drupal\media_acquiadam\Entity;

/**
 * The asset entity describing the asset object shared by Acquia DAM.
 *
 * @phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerCamelName
 */
class Asset implements EntityInterface, \JsonSerializable {

  /**
   * The ID of the asset.
   *
   * @var string
   */
  public $id;

  /**
   * The external ID of the asset.
   *
   * @var string
   */
  public $external_id;

  /**
   * The filename of the asset.
   *
   * @var string
   */
  public $filename;

  /**
   * The date the asset has been created (format: YYYY-MM-DDTHH:MM:SSZ).
   *
   * @var string
   */
  public $created_date;

  /**
   * The latest date the asset has been updated (format: YYYY-MM-DDTHH:MM:SSZ).
   *
   * @var string
   */
  public $last_update_date;

  /**
   * The date the file has been uploaded (format: YYYY-MM-DDTHH:MM:SSZ).
   *
   * @var string
   */
  public $file_upload_date;

  /**
   * The date the asset has been deleted (format: YYYY-MM-DDTHH:MM:SSZ).
   *
   * @var string
   */
  public $deleted_date;

  /**
   * Flag assets which are released and not expired.
   *
   * @var bool
   */
  public $released_and_not_expired;

  /**
   * The link to download the asset.
   *
   * @var array
   */
  public $download_link;

  /**
   * An array of thumbnail urls.
   *
   * @var array
   */
  public $thumbnails;

  /**
   * The list of the asset's properties.
   *
   * @var array
   */
  public $asset_properties;

  /**
   * A list of urls to embed the asset.
   *
   * @var object
   */
  public $embeds;

  /**
   * The list of the file's properties.
   *
   * @var array
   */
  public $file_properties;

  /**
   * The asset's metadata.
   *
   * @var array
   */
  public $metadata;

  /**
   * The description of the asset's metadata types.
   *
   * @var array
   */
  public $metadata_info;

  /**
   * The possible values of the metadata of vocabulary types.
   *
   * @var array
   */
  public $metadata_vocabulary;

  /**
   * The asset's security metadata.
   *
   * @var array
   */
  public $security;

  /**
   * The list of the expanded attributes.
   *
   * @var array
   */
  public $expanded;

  /**
   * Various links related to the asset.
   *
   * @var object
   */
  public $links;

  /**
   * A list of allowed values for the "expand" query attribute.
   *
   * @return string[]
   *   The exhaustive list of allowed "expand" values.
   */
  public static function getAllowedExpands(): array {
    return [
      'asset_properties',
      'file_properties',
      'metadata',
      'metadata_info',
      'metadata_vocabulary',
      'security',
      'status',
      'thumbnails',
      'embeds',
    ];
  }

  /**
   * The default expand query attribute.
   *
   * These attributes are mandatory for some later process.
   *
   * @return string[]
   *   The list of expands properties which must be fetched along the asset.
   */
  public static function getRequiredExpands(): array {
    return [
      'file_properties',
      'metadata',
      'embeds',
      'security',
    ];
  }

  /**
   * Acquia DAM supported file formats.
   *
   * @todo Get these values from Config.
   * @todo Check if the values should be translatable.
   *
   * @return string[]
   *   An array of supported file formats.
   */
  public static function getFileFormats(): array {
    return [
      0 => 'All',
      'IMAGE' => 'Image',
      'video' => 'Video',
      'pdf' => 'PDF',
      'AUDIO' => 'Audio',
      'COMPRESSED_ARCHIVE' => 'Archive',
      'EBOOK' => 'Ebook',
      'GENERIC_BINARY' => 'Generic Binary',
      'OFFICE' => 'Office',
      'OPEN_OFFICE' => 'Open Office',
      'INDESIGN' => 'Indesign',
      'PROJECT_ARCHIVE' => 'Project Archive',
      'SHOCKWAVE' => 'Shockwave',
      'ZOOM' => 'Zoom',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fromJson($json) {
    if (is_string($json)) {
      $json = json_decode($json);
    }

    $properties = [
      'id',
      'external_id',
      'filename',
      'created_date',
      'last_update_date',
      'file_upload_date',
      'deleted_date',
      'released_and_not_expired',
      'download_link',
      'thumbnails',
      'asset_properties',
      'embeds',
      'file_properties',
      'metadata',
      'metadata_info',
      'metadata_vocabulary',
      'security',
      'expanded',
      '_links',
    ];

    // Copy all the simple properties.
    $asset = new static();
    foreach ($properties as $property) {
      if (isset($json->{$property})) {
        if ($property === '_links') {
          $asset->links = $json->{$property};
        }
        else {
          $asset->{$property} = $json->{$property};
        }
      }
    }

    return $asset;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize():array {
    return [
      'id' => $this->id,
      'external_id' => $this->external_id,
      'filename' => $this->filename,
      'created_date' => $this->created_date,
      'last_update_date' => $this->last_update_date,
      'file_upload_date' => $this->file_upload_date,
      'deleted_date' => $this->deleted_date,
      'released_and_not_expired' => $this->released_and_not_expired,
      'download_link' => $this->download_link,
      'thumbnails' => $this->thumbnails,
      'asset_properties' => $this->asset_properties,
      'embeds' => $this->embeds,
      'file_properties' => $this->file_properties,
      'metadata' => $this->metadata,
      'metadata_info' => $this->metadata_info,
      'metadata_vocabulary' => $this->metadata_vocabulary,
      'security' => $this->security,
      'expanded' => $this->expanded,
      '_links' => $this->links,
    ];
  }

}
