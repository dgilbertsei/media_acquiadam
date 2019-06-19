<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use cweagans\webdam\Entity\Asset;
use Drupal;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Token;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\AssetDataInterface;
use Drupal\media_acquiadam\Client as DAMClient;
use Drupal\media_acquiadam\ClientFactory;
use Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

/**
 * Asset plugin test.
 *
 * @group media_acquiadam
 */
class AcquiadamAssetPluginTest extends UnitTestCase {

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Validate we fetch the correct thumbnail size when given varying sizes.
   */
  public function testGetThumbnailUrlBySize() {
    $media_source = $this->getAcquiadamMediaSource();
    $asset = $this->getAssetData();

    // Ensure that we get the smallest size when given something smaller than
    // set.
    $tn_url = $media_source->getThumbnailUrlBySize($asset, 50);
    $this->assertEquals('http://subdomain.webdamdb.com/s/100th_sm_0UerYozlI3.jpg', $tn_url);

    // Ensure we can get an exact size.
    $tn_url = $media_source->getThumbnailUrlBySize($asset, 100);
    $this->assertEquals('http://subdomain.webdamdb.com/s/100th_sm_0UerYozlI3.jpg', $tn_url);

    // Ensure we get the closest smallest if available.
    $tn_url = $media_source->getThumbnailUrlBySize($asset, 120);
    $this->assertEquals('http://subdomain.webdamdb.com/s/100th_sm_0UerYozlI3.jpg', $tn_url);

    // Ensure we get the closest smallest for larger sizes.
    $tn_url = $media_source->getThumbnailUrlBySize($asset, 350);
    $this->assertEquals('http://subdomain.webdamdb.com/s/310th_sm_0UerYozlI3.jpg', $tn_url);

    // Ensure we get the  biggest if nothing was available.
    $tn_url = $media_source->getThumbnailUrlBySize($asset, 12000);
    $this->assertEquals('http://subdomain.webdamdb.com/s/md_0UerYozlI3.jpg', $tn_url);

    // Ensure we get the biggest when nothing is specified.
    $tn_url = $media_source->getThumbnailUrlBySize($asset);
    $this->assertEquals('http://subdomain.webdamdb.com/s/md_0UerYozlI3.jpg', $tn_url);
  }

  /**
   * Create an AcquiadamAsset media source object.
   *
   * @return \Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset
   *   The AcquiadamAsset media source.
   */
  protected function getAcquiadamMediaSource() {
    $media_source = AcquiadamAsset::create(Drupal::getContainer(), ['source_field' => 'source'], 'test_plugin', []);
    return $media_source;
  }

  /**
   * Returns an Asset object for testing against.
   *
   * @return \cweagans\webdam\Entity\Asset
   *   A hard-coded Asset item.
   */
  protected function getAssetData() {
    $asset_info = [
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
      'datecreated' => '2012-12-13 13:50:10',
      'datemodified' => '2013-08-17 14:44:15',
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

    return Asset::fromJson(json_encode($asset_info));
  }

  /**
   * Test that all basic attributes are set and XMP metadata gets set.
   */
  public function testGetMetadataAttributes() {
    $media_source = $this->getAcquiadamMediaSource();
    $attributes = $media_source->getMetadataAttributes();

    $this->assertArrayHasKey('colorspace', $attributes);
    $this->assertArrayHasKey('datecaptured', $attributes);
    $this->assertArrayHasKey('datecreated', $attributes);
    $this->assertArrayHasKey('datemodified', $attributes);
    $this->assertArrayHasKey('description', $attributes);
    $this->assertArrayHasKey('file', $attributes);
    $this->assertArrayHasKey('filename', $attributes);
    $this->assertArrayHasKey('filesize', $attributes);
    $this->assertArrayHasKey('filetype', $attributes);
    $this->assertArrayHasKey('folderID', $attributes);
    $this->assertArrayHasKey('height', $attributes);
    $this->assertArrayHasKey('status', $attributes);
    $this->assertArrayHasKey('type', $attributes);
    $this->assertArrayHasKey('id', $attributes);
    $this->assertArrayHasKey('version', $attributes);
    $this->assertArrayHasKey('width', $attributes);

    $this->assertArrayHasKey('xmp_example_xmp_1', $attributes);
    $this->assertArrayHasKey('xmp_example_xmp_2', $attributes);
    $this->assertArrayHasKey('xmp_example_xmp_3', $attributes);

    $this->assertArrayNotHasKey('missing_attribute', $attributes);
    $this->assertArrayNotHasKey('xmp_missing_xmp_1', $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigFactoryStub(array $configs = []) {
    return parent::getConfigFactoryStub([
      'media_acquiadam.settings' => [
        'username' => 'WDusername',
        'password' => 'WDpassword',
        'client_id' => 'WDclient-id',
        'secret' => 'WDsecret',
        'sync_interval' => '14400',
        'size_limit' => 1280,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->container = new ContainerBuilder();

    $logger_channel = $this->getMockBuilder(LoggerChannelInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger_factory = $this->getMockBuilder(LoggerChannelFactoryInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger_factory->expects($this->any())
      ->method('get')
      ->with('media_acquiadam')
      ->willReturn($logger_channel);
    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_field_manager = $this->getMockBuilder(EntityFieldManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $field_type_plugin_manager = $this->getMockBuilder(FieldTypePluginManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $token = $this->getMockBuilder(Token::class)
      ->disableOriginalConstructor()
      ->getMock();
    $image_factory = $this->getMockBuilder(ImageFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $mime_type_guesser = $this->getMockBuilder(MimeTypeGuesserInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $http_client = $this->getMockBuilder(GuzzleClient::class)
      ->disableOriginalConstructor()
      ->getMock();
    $file_system = $this->getMockBuilder(FileSystemInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container->set('string_translation', $this->getStringTranslationStub());
    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('entity_field.manager', $entity_field_manager);
    $this->container->set('plugin.manager.field.field_type', $field_type_plugin_manager);
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('file_system', $file_system);
    $this->container->set('token', $token);
    $this->container->set('logger.factory', $logger_factory);
    $this->container->set('image.factory', $image_factory);
    $this->container->set('file.mime_type.guesser', $mime_type_guesser);
    $this->container->set('http_client', $http_client);

    $dam_client = $this->getMockBuilder(DAMClient::class)
      ->disableOriginalConstructor()
      ->getMock();
    $dam_client->expects($this->any())
      ->method('getActiveXmpFields')
      ->willReturn([
        'xmp_example_xmp_1' => [
          'name' => 'example_xmp_1',
          'label' => 'Example XMP 1',
          'type' => 'string',
        ],
        'xmp_example_xmp_2' => [
          'name' => 'example_xmp_2',
          'label' => 'Example XMP 2',
          'type' => 'string',
        ],
        'xmp_example_xmp_3' => [
          'name' => 'example_xmp_3',
          'label' => 'Example XMP 3',
          'type' => 'string',
        ],
      ]);
    $acquiadam_client_factory = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $acquiadam_client_factory->expects($this->any())
      ->method('get')
      ->willReturn($dam_client);

    $asset_data = $this->getMockBuilder(AssetDataInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container->set('media_acquiadam.asset_data', $asset_data);
    $this->container->set('media_acquiadam.client_factory', $acquiadam_client_factory);

    $acquiadam = Acquiadam::create($this->container, [], '', []);
    $this->container->set('media_acquiadam.acquiadam', $acquiadam);

    Drupal::setContainer($this->container);
  }

}
