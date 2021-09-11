<?php

namespace Drupal\Tests\acquiadam\Unit;

use cweagans\webdam\Entity\Asset;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\acquiadam\Acquiadam;
use Drupal\acquiadam\AssetDataInterface;
use Drupal\acquiadam\Client;
use Drupal\acquiadam\ClientFactory;
use Drupal\Tests\acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\acquiadam\Traits\AcquiadamLoggerFactoryTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Acquia DAM REST extension tests.
 *
 * @group acquiadam
 */
class AcquiadamServiceTest extends UnitTestCase {

  use AcquiadamConfigTrait, AcquiadamLoggerFactoryTrait, AcquiadamAssetDataTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Media: Acquia DAM client.
   *
   * @var \Drupal\acquiadam\Acquiadam
   */
  protected $acquiaDamClient;

  /**
   * Validate the static cache helper works as expected.
   */
  public function testStaticAssetCache() {
    $asset = $this->getAssetData();

    $this->assertNull($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));

    $this->acquiaDamClient->staticAssetCache('set', $asset->id, FALSE);
    $this->assertFalse($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));

    $this->acquiaDamClient->staticAssetCache('set', $asset->id, $asset);
    $this->assertInstanceOf(Asset::class,
      $this->acquiaDamClient->staticAssetCache('get', $asset->id));

    $this->acquiaDamClient->staticAssetCache('clear');
    $this->assertNull($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));
  }

  /**
   * Validates we can fetch an asset.
   */
  public function testGetAsset() {
    $asset = $this->getAssetData();

    // No assets should be primed.
    $this->assertNull($this->acquiaDamClient->staticAssetCache('get',
      $asset->id));

    // Asset should be primed.
    $this->assertInstanceOf(Asset::class,
      $this->acquiaDamClient->getAsset($asset->id));
    $this->assertInstanceOf(Asset::class,
      $this->acquiaDamClient->staticAssetCache('get', $asset->id));

    // Simulate a cached failed fetch.
    $this->acquiaDamClient->staticAssetCache('set', $asset->id, FALSE);
    $this->assertFalse($this->acquiaDamClient->getAsset($asset->id));

    $this->assertFalse($this->acquiaDamClient->getAsset(1234567890));
  }

  /**
   * Tests that flattened folder output matches what is expected.
   */
  public function testGetFlattenedFolderList() {
    // Test that the top level flattening works as expected.
    $folders = $this->acquiaDamClient->getFlattenedFolderList();
    $this->assertArrayEquals($this->getFlattenedTopLevelFoldersData(),
      $folders);

    // Test that a parent item gets its proper child items.
    $folders = $this->acquiaDamClient->getFlattenedFolderList(90672);
    $this->assertArrayEquals([
      90673 => 'Slideshows',
      90674 => 'Ad Ideas',
    ],
      $folders);

    // Test that a parent item gets its proper child items.
    $folders = $this->acquiaDamClient->getFlattenedFolderList(90786);
    $this->assertArrayEquals([
      90787 => 'Spreadsheets',
      90788 => 'Logos',
    ],
      $folders);

    // Test that items with no children reflect that.
    $folders = $this->acquiaDamClient->getFlattenedFolderList(90832);
    $this->assertArrayEquals([], $folders);

    // Test that child items with no subchildren reflect that.
    $folders = $this->acquiaDamClient->getFlattenedFolderList(90673);
    $this->assertArrayEquals([], $folders);
  }

  /**
   * Validate our helper method for testing folder data works as expected.
   */
  public function testSelfGetFolderData() {
    // Test that we can get parent folders.
    $folder = $this->getFolderData(90786);
    $this->assertObjectHasAttribute('id', $folder);
    $this->assertEquals(90786, $folder->id);

    // Test that we can get child folders.
    $folder = $this->getFolderData(90788);
    $this->assertObjectHasAttribute('id', $folder);
    $this->assertEquals(90788, $folder->id);
  }

  /**
   * The flattened top level folder data.
   *
   * @return array
   *   The array of flattened folders keyed by ID.
   */
  protected function getFlattenedTopLevelFoldersData() {
    return [
      90672 => 'Marketing',
      90673 => 'Slideshows',
      90674 => 'Ad Ideas',
      90786 => 'Sales',
      90787 => 'Spreadsheets',
      90788 => 'Logos',
      90832 => 'Support',
    ];
  }

  /**
   * Get the specific folder from an array of folders.
   *
   * @param int $folderId
   *   The ID of the folder to retrieve.
   * @param array $folders
   *   The array of folders to look for. Defaults to getTopLevelFoldersData().
   *
   * @return object|null
   *   The folder if found, NULL otherwise.
   */
  protected function getFolderData($folderId, array $folders = []) {
    if (empty($folders)) {
      $folders = $this->getTopLevelFoldersData();
    }

    foreach ($folders as $folder) {
      if (!empty($folder->id) && $folder->id == $folderId) {
        return $folder;
      }
      elseif (!empty($folder->folders)) {
        $child = $this->getFolderData($folderId, $folder->folders);
        if (!empty($child)) {
          return $child;
        }
      }
    }
    return NULL;
  }

  /**
   * Sample data for the top level folder content.
   *
   * @return array
   *   A minimal array of test content as the API would return.
   */
  protected function getTopLevelFoldersData() {
    return [
      (object) [
        'id' => '90672',
        'name' => 'Marketing',
        'folders' => [
          (object) [
            'id' => '90673',
            'name' => 'Slideshows',
          ],
          (object) [
            'id' => '90674',
            'name' => 'Ad Ideas',
          ],
        ],
      ],
      (object) [
        'id' => '90786',
        'name' => 'Sales',
        'folders' => [
          (object) [
            'id' => '90787',
            'name' => 'Spreadsheets',
          ],
          (object) [
            'id' => '90788',
            'name' => 'Logos',
          ],
        ],
      ],
      (object) [
        'id' => '90832',
        'name' => 'Support',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $dam_client = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->getMock();
    $dam_client->expects($this->any())
      ->method('getFolder')
      ->willReturnCallback(function ($folderId) {
        return $this->getFolderData($folderId);
      });
    $dam_client->expects($this->any())
      ->method('getTopLevelFolders')
      ->willReturn($this->getTopLevelFoldersData());
    $dam_client->method('getAsset')->willReturnMap([
      [$this->getAssetData()->id, TRUE, $this->getAssetData()],
      [$this->getAssetData()->id, FALSE, $this->getAssetData()],
    ]);

    // We need to make sure we get our mocked class instead of the original.
    $acquiadam_client_factory = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $acquiadam_client_factory->expects($this->any())
      ->method('get')
      ->willReturn($dam_client);

    $acquiadam_asset_data = $this->getMockBuilder(AssetDataInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation',
      $this->getStringTranslationStub());
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('logger.factory', $this->getLoggerFactoryStub());
    $this->container->set('acquiadam.client_factory',
      $acquiadam_client_factory);
    $this->container->set('acquiadam.asset_data', $acquiadam_asset_data);
    \Drupal::setContainer($this->container);

    $this->acquiaDamClient = Acquiadam::create($this->container);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    // Reset the static cache because it will persist between tests.
    $this->acquiaDamClient->staticAssetCache('clear');
  }

}
