<?php

/**
 * @file
 * Describes Acquia DAM's Asset data type.
 */

namespace Drupal\acquiadam\Entity;

class Asset implements EntityInterface, \JsonSerializable {

  /**
   * @var string $id
   */
  public $id;

  /**
   * @var string $external_id
   */
  public $external_id;

  /**
   * @var string $filename
   */
  public $filename;

  /**
   * @var string $created_date
   */
  public $created_date;

  /**
   * @var string $last_update_date
   */
  public $last_update_date;

  /**
   * @var string $file_upload_date
   */
  public $file_upload_date;

  /**
   * @var string $deleted_date
   */
  public $deleted_date;

  /**
   * @var boolean $released_and_not_expired
   */
  public $released_and_not_expired;

  /**
   * @var array $download_link
   */
  public $download_link;

  /**
   * @var array $thumbnails
   */
  public $thumbnails;

  public function getAllowedExpands() {
    return [
      'asset_properties',
      'file_properties',
      'metadata',
      'metadata_info',
      'metadata_vocabulary',
      'security',
      'status',
      'thumbnails'
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
      'thumbnails'
    ];

    // Copy all of the simple properties.
    $asset = new static();
    foreach ($properties as $property) {
      if (isset($json->{$property})) {
        $asset->{$property} = $json->{$property};
      }
    }

    return $asset;
  }

  public function jsonSerialize() {
    $properties = [
      'id' => $this->id,
      'external_id' => $this->external_id,
      'filename' => $this->filename,
      'created_date' => $this->created_date,
      'last_update_date' => $this->last_update_date,
      'file_upload_date' => $this->file_upload_date,
      'deleted_date' => $this->deleted_date,
      'released_and_not_expired' => $this->released_and_not_expired,
      'download_link' => $this->download_link,
      'thumbnails' => $this->thumbnails
    ];

    return $properties;
  }

}
