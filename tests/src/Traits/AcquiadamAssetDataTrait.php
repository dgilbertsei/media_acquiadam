<?php

namespace Drupal\Tests\acquiadam\Traits;

use cweagans\webdam\Entity\Asset;

/**
 * Shared asset data.
 */
trait AcquiadamAssetDataTrait {

  /**
   * Returns an Asset object for testing against.
   *
   * @param array $values
   *   Extra values for the asset.
   *
   * @return \cweagans\webdam\Entity\Asset
   *   A hard-coded Asset item.
   */
  protected function getAssetData(array $values = []) {
    $asset_info = $values + [
      'type' => 'asset',
      'id' => 3455969,
      'filename' => 'XAAAZZZZZ.jpg',
      'name' => 'Micro turbine 60',
      'filesize' => '0.07',
      'width' => 647,
      'height' => 433,
      'description' => 'micro-turbine, 60- or 100-kilowatt wind turbines',
      'filetype' => 'jpg',
      'colorspace' => 'RGB',
      'version' => 4,
      'type_id' => 1,
      'datecreated' => '2017-03-22 18:34:43',
      'date_created_unix' => '1490207683',
      'datemodified' => '2017-03-22 18:36:33',
      'date_modified_unix' => '1490207793',
      'datecaptured' => '2013-03-19 14:16:49',
      'datecapturedUnix' => '1363702609',
      'thumbnailurls' => [
        [
          'size' => 100,
          'url' => 'http://subdomain.webdamdb.com/s/100th_sm_0UerYozlI3.jpg',
        ],
        [
          'size' => 150,
          'url' => 'http://subdomain.webdamdb.com/s/150th_sm_0UerYozlI3.jpg',
        ],
        [
          'size' => 220,
          'url' => 'http://subdomain.webdamdb.com/s/220th_sm_0UerYozlI3.jpg',
        ],
        [
          'size' => 310,
          'url' => 'http://subdomain.webdamdb.com/s/310th_sm_0UerYozlI3.jpg',
        ],
        [
          'size' => 550,
          'url' => 'http://subdomain.webdamdb.com/s/md_0UerYozlI3.jpg',
        ],
      ],
      'folder' => [
        'type' => 'folder',
        'id' => 90754,
        'name' => "Jody's New Folder - Don't Touch",
      ],
      'user' => [
        'type' => 'user',
        'id' => 9750,
        'email' => 'jsmith@webdamdb.com',
        'name' => 'John Smith',
        'username' => 'myusername',
      ],
    ];

    $asset = Asset::fromJson(json_encode($asset_info));

    // XMP metadata must be set after the Asset object is created.
    $asset->xmp_metadata = [
      'caption' => [
        'name' => 'Caption/Abstract',
        'label' => 'Caption/Description',
        'type' => 'textarea',
        'value' => 'XMP Caption',
      ],
      'byline' => [
        'name' => 'By-line',
        'label' => 'Photographer',
        'type' => 'text',
        'value' => 'XMP Byline',
      ],
    ];

    return $asset;
  }

  /**
   * Create a new version of a given asset.
   *
   * @param \cweagans\webdam\Entity\Asset $asset
   *   The asset to be updated.
   *
   * @return \cweagans\webdam\Entity\Asset
   *   The updated asset.
   */
  protected function generateNewVersion(Asset $asset) {
    $asset->version++;

    $filename_parts = explode('.', $asset->filename);
    $asset->filename = $filename_parts[0] . '_version_' . $asset->version . '.' . $filename_parts[1];

    return $asset;
  }

}
