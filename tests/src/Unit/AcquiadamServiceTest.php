<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\Client;
use Drupal\media_acquiadam\ClientFactory;
use Drupal\Tests\UnitTestCase;

/**
 * Acquia DAM REST extension tests.
 *
 * @group media_acquiadam
 */
class AcquiadamServiceTest extends UnitTestCase {

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Tests that flattened folder output matches what is expected.
   */
  public function testGetFlattenedFolderList() {
    // Client type does not matter; We're not using it.
    $acquiadam = new Acquiadam($this->container->get('media_acquiadam.client_factory'), 'background');

    // Test that the top level flattening works as expected.
    $folders = $acquiadam->getFlattenedFolderList();
    $this->assertArrayEquals($this->getFlattenedTopLevelFoldersData(), $folders);

    // Test that a parent item gets its proper child items.
    $folders = $acquiadam->getFlattenedFolderList(90672);
    $this->assertArrayEquals([
      90673 => 'Slideshows',
      90674 => 'Ad Ideas',
    ], $folders);

    // Test that a parent item gets its proper child items.
    $folders = $acquiadam->getFlattenedFolderList(90786);
    $this->assertArrayEquals([
      90787 => 'Spreadsheets',
      90788 => 'Logos',
    ], $folders);

    // Test that items with no children reflect that.
    $folders = $acquiadam->getFlattenedFolderList(90832);
    $this->assertArrayEquals([], $folders);

    // Test that child items with no subchildren reflect that.
    $folders = $acquiadam->getFlattenedFolderList(90673);
    $this->assertArrayEquals([], $folders);
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

    // We need to make sure we get our mocked class instead of the original.
    $acquiadam_client_factory = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $acquiadam_client_factory->expects($this->any())
      ->method('get')
      ->willReturn($dam_client);

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation', $this->getStringTranslationStub());
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('media_acquiadam.client_factory', $acquiadam_client_factory);

    Drupal::setContainer($this->container);
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

}
