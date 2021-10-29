<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\Service\AssetMetadataHelper;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests integration of the AssetMetadataHelper service.
 *
 * @group media_acquiadam
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
   * Acquia DAM asset metadata helper service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetMetadataHelper
   */
  protected $assetMetadataHelper;

  /**
   * Test that all basic attributes are set and specific metadata gets set.
   */
  public function testGetMetadataAttributeLabels() {
    $this->assetMetadataHelper->setSpecificMetadataFields([
      'author' => [
        'label' => "author",
        'type' => "string",
      ],
    ]);
    $attributes = $this->assetMetadataHelper->getMetadataAttributeLabels();

    $this->assertArrayHasKey('file_upload_date', $attributes);
    $this->assertArrayHasKey('created_date', $attributes);
    $this->assertArrayHasKey('last_update_date', $attributes);
    $this->assertArrayHasKey('filename', $attributes);
    $this->assertArrayHasKey('external_id', $attributes);
    $this->assertArrayHasKey('deleted_date', $attributes);
    $this->assertArrayHasKey('released_and_not_expired', $attributes);
    $this->assertArrayHasKey('expiration_date', $attributes);
    $this->assertArrayHasKey('release_date', $attributes);
    $this->assertArrayHasKey('format', $attributes);
    $this->assertArrayHasKey('file', $attributes);
    $this->assertArrayHasKey('height', $attributes);
    $this->assertArrayHasKey('width', $attributes);
    $this->assertArrayHasKey('popularity', $attributes);
    $this->assertArrayHasKey('author', $attributes);
    $this->assertArrayNotHasKey('missing_attribute', $attributes);
  }

  /**
   * Validate that we can retrieve complicated metadata from assets.
   */
  public function testGetMetadataFromAsset() {
    $this->assetMetadataHelper->setSpecificMetadataFields([
      'author' => [
        'label' => "author",
        'type' => "string",
      ],
    ]);

    $this->assertEquals("demoextid",
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'external_id'));
    $this->assertEquals('theHumanRaceMakesSense.jpg',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'filename'));

    // Check date properties.
    $this->assertEquals('2021-09-24T18:31:02Z',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'created_date'));
    $this->assertEquals('2021-09-27T12:21:21Z',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'last_update_date'));
    $this->assertEquals('2021-09-24T18:31:02Z',
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'file_upload_date'));

    $this->assertEquals(NULL,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'deleted_date'));
    $this->assertEquals(TRUE,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'released_and_not_expired'));
    $this->assertEquals(0,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'popularity'));

    $this->assertEquals(85,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'size_in_kbytes'));
    $this->assertEquals(650,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'height'));
    $this->assertEquals(650,
      $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
        'width'));

    $this->assertEquals(NULL,
    $this->assetMetadataHelper->getMetadataFromAsset($this->getAssetData(),
      'author'));

  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $date_formatter = $this->getMockBuilder(DateFormatterInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $date_formatter->method('format')
      ->willReturnCallback(function ($timestamp, $type, $format) {
        if ('custom' == $type) {
          $dt = new \DateTime('now', new \DateTimeZone('UTC'));
          $dt->setTimestamp($timestamp);
          return $dt->format($format);
        }
        return FALSE;
      });

    $acquiadam_client = $this->getMockBuilder(Acquiadam::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation',
      $this->getStringTranslationStub());
    $this->container->set('date.formatter', $date_formatter);
    $this->container->set('media_acquiadam.acquiadam', $acquiadam_client);
    \Drupal::setContainer($this->container);

    $this->assetMetadataHelper = AssetMetadataHelper::create($this->container);
  }

}
