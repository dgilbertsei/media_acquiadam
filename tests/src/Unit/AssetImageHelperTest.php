<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\media_acquiadam\Service\AssetImageHelper;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

/**
 * Tests integration of the AssetImageHelper service.
 *
 * @gruop media_acquiadam
 */
class AssetImageHelperTest extends UnitTestCase {

  use AcquiadamAssetDataTrait, AcquiadamConfigTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * @var \Drupal\media_acquiadam\Service\AssetImageHelper|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $assetImageHelper;

  /**
   * Validate we fetch the correct thumbnail size when given varying sizes.
   */
  public function testGetThumbnailUrlBySize() {
    $asset = $this->getAssetData();

    // Ensure that we get the smallest size when given something smaller than
    // set.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 50);
    $this->assertEquals('http://subdomain.webdamdb.com/s/100th_sm_0UerYozlI3.jpg',
      $tn_url);

    // Ensure we can get an exact size.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 100);
    $this->assertEquals('http://subdomain.webdamdb.com/s/100th_sm_0UerYozlI3.jpg',
      $tn_url);

    // Ensure we get the closest smallest if available.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 120);
    $this->assertEquals('http://subdomain.webdamdb.com/s/100th_sm_0UerYozlI3.jpg',
      $tn_url);

    // Ensure we get the closest smallest for larger sizes.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 350);
    $this->assertEquals('http://subdomain.webdamdb.com/s/310th_sm_0UerYozlI3.jpg',
      $tn_url);

    // Ensure we get the  biggest if nothing was available.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 12000);
    $this->assertEquals('http://subdomain.webdamdb.com/s/md_0UerYozlI3.jpg',
      $tn_url);

    // Ensure we get the biggest when nothing is specified.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset);
    $this->assertEquals('http://subdomain.webdamdb.com/s/md_0UerYozlI3.jpg',
      $tn_url);
  }

  /**
   * Validate that a fallback image can be found.
   */
  public function testGetFallbackThumbnail() {
    // First FALSE will trigger the set thumbnail method.
    // Second FALSE will trigger the file copy.
    // TRUE will trigger using the default.
    $this->assetImageHelper->method('phpFileExists')
      ->with('public://webdam.png')
      ->willReturnOnConsecutiveCalls(FALSE, FALSE, TRUE);

    $this->assertEquals('public://webdam.png_copy',
      $this->assetImageHelper->getFallbackThumbnail(),
      'File should be copied to new location');
    $this->assertEquals('public://webdam.png',
      $this->assetImageHelper->getFallbackThumbnail(),
      'Existing file should be used');
  }

  public function testGetGenericMediaIcon() {

    $mimetype = [
      'discrete' => 'image',
      'sub' => 'jpg',
    ];

    // @TODO: Any way to reset the mock method so we can change willReturn?
    // We have to get a new mock class each test because it is (seemingly) not
    // possible to change the willReturn for a mocked method after it has been
    // set.
    $helper = $this->getMockedAssetImageHelper();
    $helper->method('phpFileExists')->willReturn(TRUE);
    $this->assertEquals('public://media-icons/image-jpg.png',
      $helper->getGenericMediaIcon($mimetype));

    $helper = $this->getMockedAssetImageHelper();
    $helper->method('phpFileExists')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $this->assertEquals('public://media-icons/jpg.png',
      $helper->getGenericMediaIcon($mimetype));

    $helper = $this->getMockedAssetImageHelper();
    $helper->method('phpFileExists')
      ->willReturnOnConsecutiveCalls(FALSE, FALSE, TRUE);
    $this->assertEquals('public://media-icons/generic.png',
      $helper->getGenericMediaIcon($mimetype));

    $helper = $this->getMockedAssetImageHelper();
    $helper->method('phpFileExists')->willReturn(FALSE);
    $this->assertFalse($helper->getGenericMediaIcon($mimetype));
  }

  /**
   * Validate that we can get proper mime types based on a file extension.
   */
  public function testGetMimeTypeFromFileType() {
    $this->assertArrayEquals([
      'discrete' => 'image',
      'sub' => 'jpg',
    ],
      $this->assetImageHelper->getMimeTypeFromFileType('jpg'));

    $this->assertArrayEquals([
      'discrete' => 'video',
      'sub' => 'quicktime',
    ],
      $this->assetImageHelper->getMimeTypeFromFileType('mov'));

    $this->assertArrayEquals([
      'discrete' => 'application',
      'sub' => 'pdf',
    ],
      $this->assetImageHelper->getMimeTypeFromFileType('pdf'));

    $this->assertFalse($this->assetImageHelper->getMimeTypeFromFileType('abc123'));
  }

  /**
   * Gets a mocked version of the AssetImageHelper class.
   *
   * This is used to provide some implemented methods that would normally be
   * an issue to test.
   *
   * @return \Drupal\media_acquiadam\Service\AssetImageHelper|\PHPUnit\Framework\MockObject\MockObject
   *   A mocked AssetImageHelper object.
   */
  protected function getMockedAssetImageHelper() {
    $helper = $this->getMockBuilder(AssetImageHelper::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('file_system'),
        $this->container->get('http_client'),
        $this->container->get('file.mime_type.guesser'),
        $this->container->get('image.factory'),
      ])
      ->setMethods([
        'phpFileExists',
        'getAcquiaDamModulePath',
        'saveFallbackThumbnail',
      ])
      ->getMock();;

    $helper->method('getAcquiaDamModulePath')
      ->willReturn('modules/contrib/media_acquiadam');
    $helper->method('saveFallbackThumbnail')->willReturn(NULL);

    return $helper;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $http_client = $this->getMockBuilder(GuzzleClient::class)
      ->disableOriginalConstructor()
      ->getMock();

    $file_system = $this->getMockBuilder(FileSystemInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $file_system->method('copy')
      ->willReturnCallback(function ($source, $target) {
        return is_string($target) ? $target . '_copy' : $target . '_blah';
      });

    $mime_type_guesser = $this->getMockBuilder(MimeTypeGuesserInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $mime_type_guesser->method('guess')->willReturnCallback(function ($uri) {
      $map = [
        'public://nothing.jpg' => 'image/jpg',
        'public://nothing.mov' => 'video/quicktime',
        'public://nothing.pdf' => 'application/pdf',
      ];

      return $map[$uri] ?? FALSE;
    });

    $image_factory = $this->getMockBuilder(ImageFactory::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('http_client', $http_client);
    $this->container->set('file_system', $file_system);
    $this->container->set('file.mime_type.guesser', $mime_type_guesser);
    $this->container->set('image.factory', $image_factory);
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    Drupal::setContainer($this->container);

    $this->assetImageHelper = $this->getMockedAssetImageHelper();
  }

}
