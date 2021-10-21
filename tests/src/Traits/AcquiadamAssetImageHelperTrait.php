<?php

namespace Drupal\Tests\media_acquiadam\Traits;

use Drupal\media_acquiadam\Service\AssetImageHelper;

/**
 * Shared methods for dealing with a mocked AssetImageHelper.
 */
trait AcquiadamAssetImageHelperTrait {

  /**
   * Create a stub AssetImageHelper that implements some basic functions.
   *
   * @return \Drupal\media_acquiadam\Service\AssetImageHelper|\PHPUnit\Framework\MockObject\MockObject
   *   The stubbed service.
   */
  public function getAssetImageHelperStub() {
    $asset_image_helper = $this->getMockBuilder(AssetImageHelper::class)
      ->disableOriginalConstructor()
      ->getMock();
    $asset_image_helper->method('getMimeTypeFromFileType')->willReturnMap([
      ['jpg', ['discrete' => 'image', 'sub' => 'jpg']],
      ['mov', ['discrete' => 'quicktime', 'sub' => 'mov']],
      ['pdf', ['discrete' => 'application', 'sub' => 'pdf']],
    ]);
    $asset_image_helper->method('getThumbnailUrlBySize')
      ->willReturn('http://subdomain.webdamdb.com/s/310th_sm_0UerYozlI3.jpg');

    return $asset_image_helper;
  }

}
