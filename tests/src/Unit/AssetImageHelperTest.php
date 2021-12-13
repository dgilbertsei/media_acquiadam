<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\media_acquiadam\Service\AssetImageHelper;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Tests integration of the AssetImageHelper service.
 *
 * @group media_acquiadam
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
   * A mocked version of the AssetImageHelper service.
   *
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
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=50&q=80',
      $tn_url);

    // Ensure we can get an exact size.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 100);
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=100&q=80',
      $tn_url);

    // Ensure we get the closest smallest if available.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 120);
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=120&q=80',
      $tn_url);

    // Ensure we get the closest smallest for larger sizes.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 350);
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=350&q=80',
      $tn_url);

    // Ensure we get the  biggest if nothing was available.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, 1280);
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=1280&q=80',
      $tn_url);

    // Ensure we get the biggest when nothing is specified.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset);
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=2048&q=80',
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
      ->with('public://widen.png')
      ->willReturnOnConsecutiveCalls(FALSE, FALSE, TRUE);

    $this->assertEquals('public://widen.png_copy',
      $this->assetImageHelper->getFallbackThumbnail(),
      'File should be copied to new location');
    $this->assertEquals('public://widen.png',
      $this->assetImageHelper->getFallbackThumbnail(),
      'Existing file should be used');
  }

  /**
   * Validate we get a generic media icon when no image can be loaded.
   */
  public function testGetGenericMediaIcon() {

    $mimetype = [
      'discrete' => 'image',
      'sub' => 'jpg',
    ];

    // @todo Any way to reset the mock method so we can change willReturn?
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
  public function testGetMimeTypeFromFileUri() {
    $this->assertEquals([
      'discrete' => 'image',
      'sub' => 'jpg',
    ],
      $this->assetImageHelper->getMimeTypeFromFileUri('public://test.jpg'));

    $this->assertEquals([
      'discrete' => 'video',
      'sub' => 'quicktime',
    ],
      $this->assetImageHelper->getMimeTypeFromFileUri('public://test.mov'));

    $this->assertEquals([
      'discrete' => 'application',
      'sub' => 'pdf',
    ],
      $this->assetImageHelper->getMimeTypeFromFileUri('public://test.pdf'));

    $this->assertFalse($this->assetImageHelper->getMimeTypeFromFileUri('public://test.abc123'));
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
        $this->container->get('entity_type.manager'),
      ])
      ->setMethods([
        'phpFileExists',
        'getAcquiaDamModulePath',
        'saveFallbackThumbnail',
      ])
      ->getMock();

    $helper->method('getAcquiaDamModulePath')
      ->willReturn('modules/contrib/media_acquiadam');
    $helper->method('saveFallbackThumbnail')->willReturn(NULL);

    return $helper;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $http_client = $this->getMockBuilder(GuzzleClient::class)
      ->disableOriginalConstructor()
      ->getMock();

    $file_system = $this->getMockBuilder(FileSystem::class)
      ->disableOriginalConstructor()
      ->setMethods(['copy'])
      ->getMockForAbstractClass();
    $file_system->method('copy')
      ->willReturnCallback(function ($source, $target) {
        return is_string($target) ? $target . '_copy' : $target . '_blah';
      });

    $mime_type_guesser = $this->getMockBuilder(MimeTypeGuesser::class)
      ->disableOriginalConstructor()
      ->getMock();
    $mime_type_guesser->method('guessMimeType')->willReturnCallback(function ($uri) {
      $map = [
        'public://test.jpg' => 'image/jpg',
        'public://test.mov' => 'video/quicktime',
        'public://test.pdf' => 'application/pdf',
      ];

      return $map[$uri] ?? '';
    });

    $image_factory = $this->getMockBuilder(ImageFactory::class)
      ->disableOriginalConstructor()
      ->getMock();

    $url_assembler = $this->getMockBuilder(UnroutedUrlAssemblerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $url_assembler
      ->expects($this->any())
      ->method('assemble')
      ->willReturnMap([
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 50, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=50&q=80'],
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 100, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=100&q=80'],
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 120, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=120&q=80'],
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 350, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=350&q=80'],
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 1280, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=1280&q=80'],
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 2048, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=2048&q=80'],
      ]);

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('http_client', $http_client);
    $this->container->set('file_system', $file_system);
    $this->container->set('file.mime_type.guesser', $mime_type_guesser);
    $this->container->set('image.factory', $image_factory);
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('unrouted_url_assembler', $url_assembler);
    $this->container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($this->container);

    $this->assetImageHelper = $this->getMockedAssetImageHelper();
  }

}
