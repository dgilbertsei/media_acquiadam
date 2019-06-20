<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use DateTime;
use DateTimeZone;
use Drupal;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\media_acquiadam\Service\AssetMetadataHelper;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests integration of the AssetMetadataHelper service.
 *
 * @gruop media_acquiadam
 */
class AssetMetadataHelperTest extends UnitTestCase {

  use AcquiadamAssetDataTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Media: Acquia DAM asset metadata helper service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetMetadataHelper
   */
  protected $assetMetadataHelper;

  /**
   * Validate that we can set the available XMP metadata fields.
   */
  public function testSetMetadataXmpFields() {
    $attributes = $this->assetMetadataHelper->getMetadataAttributeLabels();
    $this->assertArrayNotHasKey('xmp_example_field', $attributes);

    $this->assetMetadataHelper->setMetadataXmpFields([
      'xmp_caption' => [
        'name' => 'Caption/Abstract',
        'label' => 'Caption/Description',
        'type' => 'textarea',
      ],
      'xmp_byline' => [
        'name' => 'By-line',
        'label' => 'Photographer',
        'type' => 'text',
      ],
    ]);

    $attributes = $this->assetMetadataHelper->getMetadataAttributeLabels();
    $this->assertArrayHasKey('xmp_byline', $attributes);
    $this->assertArrayHasKey('xmp_caption', $attributes);
  }

  /**
   * Test that all basic attributes are set and XMP metadata gets set.
   */
  public function testGetMetadataAttributeLabels() {
    $attributes = $this->assetMetadataHelper->getMetadataAttributeLabels();

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

    $this->assertArrayNotHasKey('missing_attribute', $attributes);
    $this->assertArrayNotHasKey('xmp_missing_xmp_1', $attributes);
  }

  /**
   * Validate that we can retrieve complicated metadata from assets.
   */
  public function testGetMetadataFromAsset() {
    $this->assetMetadataHelper->setMetadataXmpFields([
      'xmp_caption' => [
        'name' => 'Caption/Abstract',
        'label' => 'Caption/Description',
        'type' => 'textarea',
      ],
      'xmp_byline' => [
        'name' => 'By-line',
        'label' => 'Photographer',
        'type' => 'text',
      ],
    ]);

    // Check some regular properties.
    $this->assertEquals(3455969,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'id'));
    $this->assertEquals(4,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'version'));
    $this->assertEquals('XAAAZZZZZ.jpg',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'filename'));

    // Check special properties.
    $this->assertEquals(90754,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'folderID'));
    $this->assertEquals('Image',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'type'));
    $this->assertNull($this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
      'status'));

    // Check date properties.
    $this->assertEquals('2017-03-22 18:34:43',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datecreated'));
    $this->assertEquals('2017-03-22 18:36:33',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datemodified'));
    $this->assertEquals('2013-03-19 14:16:49',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datecaptured'));

    $this->assertEquals('2017-03-22T18:34:43',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datecreated_date'));
    $this->assertEquals('2017-03-22T18:36:33',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datemodified_date'));
    $this->assertEquals('2013-03-19T14:16:49',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datecaptured_date'));

    $this->assertEquals(1490207683,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datecreated_unix'));
    $this->assertEquals(1490207793,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datemodified_unix'));
    $this->assertEquals(1363702609,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'datecaptured_unix'));

    // Check XMP properties.
    $this->assertEquals('XMP Byline',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'xmp_byline'));
    $this->assertEquals('XMP Caption',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'xmp_caption'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $date_formatter = $this->getMockBuilder(DateFormatterInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $date_formatter->method('format')
      ->willReturnCallback(function ($timestamp, $type, $format) {
        if ('custom' == $type) {
          $dt = new DateTime('now', new DateTimeZone('UTC'));
          $dt->setTimestamp($timestamp);
          return $dt->format($format);
        }
        return FALSE;
      });

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation',
      $this->getStringTranslationStub());
    $this->container->set('date.formatter', $date_formatter);
    Drupal::setContainer($this->container);

    $this->assetMetadataHelper = AssetMetadataHelper::create($this->container);
  }

}
