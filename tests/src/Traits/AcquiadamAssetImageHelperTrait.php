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
    $asset_image_helper->method('getMimeTypeFromFileUri')->willReturnMap([
      ['public://test.jpg', ['discrete' => 'image', 'sub' => 'jpg']],
      ['public://test.mov', ['discrete' => 'quicktime', 'sub' => 'mov']],
      ['public://test.pdf', ['discrete' => 'application', 'sub' => 'pdf']],
    ]);
    $asset_image_helper->method('getThumbnailUrlBySize')
      ->willReturn('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=1280&q=80');

    return $asset_image_helper;
  }

}
