<?php

namespace Drupal\Tests\media_acquiadam\Traits;

use Drupal\media_acquiadam\Entity\Asset;

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
   * @return \Drupal\media_acquiadam\Entity\Asset
   *   A hard-coded Asset item.
   */
  protected function getAssetData(array $values = []) {

    $asset_info = $values + [
      'id' => '34asd3q2-e294-4908-bbd9-f43f433d2e23',
      'external_id' => 'demoextid',
      'filename' => 'theHumanRaceMakesSense.jpg',
      'created_date' => '2021-09-24T18:31:02Z',
      'last_update_date' => '2021-09-27T12:21:21Z',
      'file_upload_date' => '2021-09-24T18:31:02Z',
      'deleted_date' => NULL,
      'released_and_not_expired' => TRUE,
      'asset_properties' => [
        "favorite" => FALSE,
        "popularity" => 0,
        "cutline_caption" => "",
      ],
      'file_properties' => [
        'format' => 'JPEG',
        'format_type' => 'image',
        'size_in_kbytes' => 85,
        'image_properties' => [
          'width' => 650.0,
          'height' => 650.0,
          'aspect_ratio' => 1.0,
        ],
        'video_properties' => NULL,
      ],
      'metadata' => [
        'fields' => [
          "author" => [],
        ],
      ],
      'metadata_info' => NULL,
      'security' => [
        'expiration_date' => NULL,
        'release_date' => '2021-09-24T18:31:02Z',
        'asset_groups' => [
          0 => 'public',
        ],
      ],
      'status' => NULL,
      'thumbnails' => [
        '125px' => [
          'url' => 'https://previews.us-east-1.widencdn.net/preview/36614591/assets/asset-view/b9abc517-6f73-4190-a189-af6cd6a59432/thumbnail/eyJ3IjoxMjUsImgiOjEyNSwic2NvcGUiOiJhcHAifQ==?Expires=1632909600&Signature=tC8zwYmj6fcK6cPc0LeG6B2~MgeDKELzNPd~kjxny835l75MFmct7mFIU5IPBk8Ty2wNiGEHl34WVO7MeMNmeBc6bhN7ti6gVilyfa40lj-YIBxcl7ZUkBgqK0l7YydffnUnz3TFwkT218AfrhhvXLsrhgYUCozrDWettv78qa4D~vsBhgn7rS1LPpXOIYdvYOFbufNz9ip7q4qSkR4TKNl9uGovGA20FL58el9eYVb1diyvUxtPnJwCD~Q6LwZDXOQGF23sbagMkuXKssNXQSxDp2sbpgeOEsLltMCTh02GiK3AbIR1PjLktreVYPssS9Fm0MqZZKFxA4KMNUGxAw__&Key-Pair-Id=APKAJM7FVRD2EPOYUXBQ',
          'valid_until' => '2021-09-29T09:34:05Z',
        ],
        '160px' => [
          'url' => 'https://previews.us-east-1.widencdn.net/preview/36614591/assets/asset-view/b9abc517-6f73-4190-a189-af6cd6a59432/thumbnail/eyJ3IjoxNjAsImgiOjE2MCwic2NvcGUiOiJhcHAifQ==?Expires=1632909600&Signature=a5MiRbd-BYvumRKqEhVdXz46osDpr0a42uCfVdtv6iIygIKqvIwHdbiNy49vHM~LZkZ3Ghqz2~kqXiWn5UD4JY0YPM539HnNC5-So5Rf~nYVNb4koFrwwKRYfQ2dM~VdxKlTmCMAQAtQEI4qJ-TJ~OpX~1h1i2MSnW6PUETnuPXKqte1t6tKbQEYi2BPsFFMQWgwumEyyfBNuADbOhqH688p23HeRwSe0K0eJy0IwLPzAZyue7JnTRV8AphBAxSWvvUEhf4mMm4wINZrf1gX-ckAchNdjB7zPmLlHht8iBnqxZFsI-vSsK6q87pyjw3B6ZfkCLEQ0oiYh0YE1IOVCA__&Key-Pair-Id=APKAJM7FVRD2EPOYUXBQ',
          'valid_until' => '2021-09-29T09:34:05Z',
        ],
        '2048px' => [
          'url' => 'https://previews.us-east-1.widencdn.net/preview/36614591/assets/asset-view/b9abc517-6f73-4190-a189-af6cd6a59432/thumbnail/eyJ3IjoyMDQ4LCJoIjoyMDQ4LCJzY29wZSI6ImFwcCJ9?Expires=1632909600&Signature=dplW1fvq1uY4WzrvsFj6KF3g4Jn7-uxnhugOfmQ73pqH9Lph0U-VFKeQPY5H5gYfA0fJAOu4dHOf3EAGMD4nehAjDZgvcey62KflKfiBux7-puQXRqYAKSU~xok0t3FenN63YJqyE8KOH8OxdanRpfHQtbJ0PhiLOfkDZlvyHkHoQvnfF2Y-Qed17f4-QfYqcmipqmW8mYWAVuRfD9tHa78bi3q-HTQJCuYT~FdDxY4RHUd8DDwMWvEjmj~-Khd2bjxsuEjvkS51pyXXYPNj4RJQlu-f8sXHepVXM2QSl2jwmp9LoU2Z7r4hJTaUqnIc9Hig2ANuGECtO1K7SY6pdw__&Key-Pair-Id=APKAJM7FVRD2EPOYUXBQ',
          'valid_until' => '2021-09-29T09:34:05Z',
        ],
        '300px' => [
          'url' => 'https://previews.us-east-1.widencdn.net/preview/36614591/assets/asset-view/b9abc517-6f73-4190-a189-af6cd6a59432/thumbnail/eyJ3IjozMDAsImgiOjMwMCwic2NvcGUiOiJhcHAifQ==?Expires=1632909600&Signature=E-5cUw7n8CK3HzW2rzdSxqASSLr-ki3rfp0efAQ5AsyoC9vS6gIU-rDA90FJjvWKDFMao0WoQplnIFt0ZQuxJGQ6AYd2E31~bw1R-r8tM1JO1PImJVLD5h6N~il1vawqJIX6JYtrwaQtZ8HMKvsUR7LVlzLv34C3OxWO0uJygz-uWI-1SWRqmFPZXIO4SXPn7Ddp5R-erVRH-hcpSg1L8rGFs6WZkczZbRBkTY1j-eDsn77x00eDkeNdcqf5m-4IC4lmEWwoLMQeQ~lN0YgWQxFiEHh-iokccMkyHfl0OKORlR~oKnxZlohe-3uPE84vtbS7bgfuSEBP3sMFEIEAOg__&Key-Pair-Id=APKAJM7FVRD2EPOYUXBQ',
          'valid_until' => '2021-09-29T09:34:05Z',
        ],
        '600px' => [
          'url' => 'https://previews.us-east-1.widencdn.net/preview/36614591/assets/asset-view/b9abc517-6f73-4190-a189-af6cd6a59432/thumbnail/eyJ3Ijo2MDAsImgiOjYwMCwic2NvcGUiOiJhcHAifQ==?Expires=1632909600&Signature=kVVldTGnznFwDKTloWLc66HKyc6sG9ohbpWDvWiH2dICVZ6087EmKu32qdEDnc5UdjCZkS8ktA0RKJyr-3wMBrwTq9KN5NIcIyXlC4bHR07XSmVJ3otTH3NtjJ9KhMywBykhXG3Jca7Rfw4fXkbtJnNk9g1KDKX7x3GOwXoRbkfBPo3hhgW7GnfO8bL1LVhp414ynTDMZBiFux3UHwJ0o6szK234hELqmaNAb8VXyY28BRK-L2PgB5-OL9HuJ6Hfg95-pCH4zj5S26xEVNGoxr7JQ9NFlPjvVEwrOpIVXcf1hadOkiKeHCE1L~v0jnM6XjHq-yaTEKKJahhuLMxrFw__&Key-Pair-Id=APKAJM7FVRD2EPOYUXBQ',
          'valid_until' => '2021-09-29T09:34:05Z',
        ],
      ],
      'embeds' => [
        '640px-landscape' => [
          'url' => 'https://demo.widen.net/content/demoextid/jpeg/theHumanRaceMakesSense.jpeg?w=640&keep=c&crop=yes&color=cccccc&quality=80&u=lv0nkk',
          'html' => '<img width="640" alt="theHumanRaceMakesSense.jpeg" src="https://demo.widen.net/content/demoextid/jpeg/theHumanRaceMakesSense.jpeg?w=640&keep=c&crop=yes&color=cccccc&quality=80&u=lv0nkk">',
          'share' => 'https://demo.widen.net/view/thumbnail/demoextid/theHumanRaceMakesSense.jpg?t.format=web&u=lv0nkk&x.share=t',
          'apps' => [],
        ],
        '640px-portrait' => [
          'url' => 'https://demo.widen.net/content/demoextid/jpeg/theHumanRaceMakesSense.jpeg?h=640&keep=c&crop=yes&color=cccccc&quality=80&u=lv0nkk',
          'html' => '<img height="640" alt="theHumanRaceMakesSense.jpeg" src="https://demo.widen.net/content/demoextid/jpeg/theHumanRaceMakesSense.jpeg?h=640&keep=c&crop=yes&color=cccccc&quality=80&u=lv0nkk">',
          'share' => 'https://demo.widen.net/view/thumbnail/demoextid/theHumanRaceMakesSense.jpg?t.format=web&u=lv0nkk&x.share=t',
          'apps' => [],
        ],
        'original' => [
          'url' => 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true',
          'html' => '<a href="https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true" target="_blank">theHumanRaceMakesSense.jpg</a>',
          'share' => 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&x.share=t',
          'apps' => [],
        ],
        'templated' => [
          'url' => 'https://embed.widencdn.net/img/demo/demoextid/{size}px@{scale}x/theHumanRaceMakesSense.jpg?q={quality}&x.template=y',
          'html' => NULL,
          'share' => 'https://embed.widencdn.net/img/demo/demoextid/{size}px@{scale}x/theHumanRaceMakesSense.jpg?q={quality}&x.template=y',
          'apps' => [],
        ],
      ],
      'expanded' => [
        'asset_properties' => TRUE,
        'embeds' => TRUE,
        'file_properties' => TRUE,
        'metadata' => FALSE,
        'metadata_info' => FALSE,
        'metadata_vocabulary' => FALSE,
        'security' => TRUE,
        'status' => FALSE,
        'thumbnails' => TRUE,
      ],
      'links' => [
        'download' => 'https://orders-bb.us-east-1.widencdn.net/download-deferred/originals?asset_wrn=widen%3Aassets%3Aasset%3ALSRZZ%3Ademoextid&actor=wrn%3Ausers%3Auser%3A36614591%3Alv0nkk&tracking=ewogICJkb19ub3RfdHJhY2siOiBmYWxzZSwKICAiYW5vbnltb3VzIjogZmFsc2UsCiAgInZpc2l0b3JfaWQiOiBudWxsLAogICJ1c2VyX3dybiI6ICJ3aWRlbjp1c2Vyczp1c2VyOkxTUlpaOmx2MG5rayIKfQ%3D%3D&custom_metadata=ewogICJhcHBfbmFtZSI6ICJheGlvbSIsCiAgImludGVuZGVkX3VzZV9jb2RlIjogbnVsbCwKICAiaW50ZW5kZWRfdXNlX3ZhbHVlIjogbnVsbCwKICAiaW50ZW5kZWRfdXNlX2VtYWlsIjogbnVsbCwKICAiY29sbGVjdGlvbl9pZCI6IG51bGwsCiAgInBvcnRhbF9pZCI6IG51bGwsCiAgImRhbV9vcmRlcl9pZCI6IG51bGwKfQ%3D%3D&Expires=1632866400&Signature=pFue9SqXsXEyyF6u3rKcUSQ8TLCiC6Y9QMNsD0y8dYTLVcHCq5~kLgE7TMmo6vExJTOrD9T8PJQjD83mSWLEaziPKPLzca3AhWpUdSh~VxENXZbLEOb65Dsi2aBKgeWUx6XUdHgv-s-suLX3ONfgukTDwGinFXwvDgix7OmGywpjF8U-ydbXVfVEe2wtO8oM~kHoWTEcAslQAEtwUwnTmbvhNnu6glynxLlAyNJRT-N6AFpZ3Yl0Mv5xVfqlY9FZsKvLFBYzdTZLIhUenGL8EoSL~IgTbUG2DpTjBPgtHOCHqfU8h2~jwQwpmlrvCToA3R89OKG~uiOfwL-UOvvsow__&Key-Pair-Id=APKAJM7FVRD2EPOYUXBQ',
        'self' => 'https://api.widencollective.com/v2/assets/f45f7fa4-e294-4908-bbd9-685b4deb52d3',
        'self_all' => 'https://api.widencollective.com/v2/assets/f45f7fa4-e294-4908-bbd9-685b4deb52d3?expand=asset_properties%2Cembeds%2Cfile_properties%2Cmetadata%2Cmetadata_info%2Cmetadata_vocabulary%2Csecurity%2Cthumbnails',
      ],
    ];

    $asset = Asset::fromJson(json_encode($asset_info));

    return $asset;
  }

  /**
   * Create a new version of a given asset.
   *
   * @param \Drupal\media_acquiadam\Entity\Asset $asset
   *   The asset to be updated.
   *
   * @return \Drupal\media_acquiadam\Entity\Asset
   *   The updated asset.
   */
  protected function generateNewVersion(Asset $asset) {
    $asset->file_upload_date = '2021-09-28T12:21:21Z';
    $filename_parts = explode('.', $asset->filename);
    $asset->filename = $filename_parts[0] . '_version_.' . $filename_parts[1];

    return $asset;
  }

}
