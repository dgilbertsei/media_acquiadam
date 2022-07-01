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
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=650&q=80',
      $tn_url);

    // Ensure we get the biggest when nothing is specified.
    $tn_url = $this->assetImageHelper->getThumbnailUrlBySize($asset);
    $this->assertEquals('https://demo.widen.net/content/demoextid/png/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=650&q=80',
      $tn_url);
  }

  /**
   * Tests that when transcoding is turned off, the download URL is used.
   */
  public function testGetThumbnailWithoutTranscode(): void {
    $config_factory = $this->getConfigFactoryStub([
      'media_acquiadam.settings' => [
        'token' => 'demo/121someRandom1342test32st',
        'sync_interval' => 3600,
        'sync_method' => "updated_date",
        'transcode' => 'original',
        'sync_perform_delete' => 1,
        'size_limit' => 1280,
        'report_asset_usage' => 1,
        'domain' => 'subdomain.widencollective.com',
        'client_id' => 'a3mf039fd77dw67886459q90098z0980.app.widen.com',
      ],
      'system.file' => ['default_scheme' => 'public'],
      'media.settings' => ['icon_base_uri' => 'public://media-icons'],
    ]);
    $asset_image_helper = new AssetImageHelper(
      $config_factory,
      $this->container->get('file_system'),
      $this->container->get('http_client'),
      $this->container->get('file.mime_type.guesser'),
      $this->container->get('image.factory'),
      $this->container->get('entity_type.manager'),
    );
    $asset = $this->getAssetData();
    $tn_url = $asset_image_helper->getThumbnailUrlBySize($asset, 50);
    $this->assertEquals(
      'https://orders-bb.us-east-1.widencdn.net/download-deferred/originals?asset_wrn=widen%3Aassets%3Aasset%3ALSRZZ%3Ademoextid&actor=wrn%3Ausers%3Auser%3A36614591%3Alv0nkk&tracking=ewogICJkb19ub3RfdHJhY2siOiBmYWxzZSwKICAiYW5vbnltb3VzIjogZmFsc2UsCiAgInZpc2l0b3JfaWQiOiBudWxsLAogICJ1c2VyX3dybiI6ICJ3aWRlbjp1c2Vyczp1c2VyOkxTUlpaOmx2MG5rayIKfQ%3D%3D&custom_metadata=ewogICJhcHBfbmFtZSI6ICJheGlvbSIsCiAgImludGVuZGVkX3VzZV9jb2RlIjogbnVsbCwKICAiaW50ZW5kZWRfdXNlX3ZhbHVlIjogbnVsbCwKICAiaW50ZW5kZWRfdXNlX2VtYWlsIjogbnVsbCwKICAiY29sbGVjdGlvbl9pZCI6IG51bGwsCiAgInBvcnRhbF9pZCI6IG51bGwsCiAgImRhbV9vcmRlcl9pZCI6IG51bGwKfQ%3D%3D&Expires=1632866400&Signature=pFue9SqXsXEyyF6u3rKcUSQ8TLCiC6Y9QMNsD0y8dYTLVcHCq5~kLgE7TMmo6vExJTOrD9T8PJQjD83mSWLEaziPKPLzca3AhWpUdSh~VxENXZbLEOb65Dsi2aBKgeWUx6XUdHgv-s-suLX3ONfgukTDwGinFXwvDgix7OmGywpjF8U-ydbXVfVEe2wtO8oM~kHoWTEcAslQAEtwUwnTmbvhNnu6glynxLlAyNJRT-N6AFpZ3Yl0Mv5xVfqlY9FZsKvLFBYzdTZLIhUenGL8EoSL~IgTbUG2DpTjBPgtHOCHqfU8h2~jwQwpmlrvCToA3R89OKG~uiOfwL-UOvvsow__&Key-Pair-Id=APKAJM7FVRD2EPOYUXBQ',
      $tn_url
    );
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
      ->onlyMethods([
        'phpFileExists',
        'saveFallbackThumbnail',
      ])
      ->getMock();
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
      ->onlyMethods(['copy'])
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
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 650, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=650&q=80'],
        ['https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true', ['query' => ['h' => 650, 'q' => 80], 'external' => TRUE], FALSE, 'https://demo.widen.net/content/demoextid/original/theHumanRaceMakesSense.jpg?u=lv0nkk&download=true&h=650&q=80'],
      ]);

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('http_client', $http_client);
    $this->container->set('file_system', $file_system);
    $this->container->set('file.mime_type.guesser', $mime_type_guesser);
    $this->container->set('image.factory', $image_factory);
    $this->container->set('config.factory', $this->getDefaultConfigFactoryStub());
    $this->container->set('unrouted_url_assembler', $url_assembler);
    $this->container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($this->container);

    $this->assetImageHelper = $this->getMockedAssetImageHelper();
  }

}
