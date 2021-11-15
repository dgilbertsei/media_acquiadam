<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\Service\AssetFileEntityHelper;
use Drupal\media_acquiadam\Service\AssetMediaFactory;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetDataTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamAssetImageHelperTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamLoggerFactoryTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamMockedMediaEntityTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests to validate that our file entity helper works as expected.
 *
 * @group media_acquiadam
 */
class AssetFileEntityHelperTest extends UnitTestCase {

  use AcquiadamAssetDataTrait, AcquiadamConfigTrait, AcquiadamAssetImageHelperTrait, AcquiadamMockedMediaEntityTrait, AcquiadamLoggerFactoryTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * A mocked AssetFileEntityHelper.
   *
   * @var \Drupal\media_acquiadam\Service\AssetFileEntityHelper|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $assetFileEntityHelper;

  /**
   * A mocked file entity.
   *
   * @var \Drupal\file\FileInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockedFileEntity;

  /**
   * Validates that the file destination builds correctly.
   */
  public function testGetDestinationFromEntity() {
    $media = $this->getMockedMediaEntity($this->getAssetData()->id);

    $this->assertEquals('private://assets/replaced',
      $this->assetFileEntityHelper->getDestinationFromEntity($media,
        'phpunit_file_field'));

    $this->assertEquals('public://acquiadam_assets',
      $this->assetFileEntityHelper->getDestinationFromEntity($media,
        'phpunit_test_fail'));
  }

  /**
   * Validates we can create a new file.
   */
  public function testCreateNewFile() {
    $asset = $this->getAssetData();

    $this->assertInstanceOf(FileInterface::class,
      $this->assetFileEntityHelper->createNewFile($asset,
        'private://assets/replaced'));
    $this->assertFalse($this->assetFileEntityHelper->createNewFile($asset,
      'random://bad/folder'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mockedFileEntity = $this->getMockBuilder(FileInterface::class)
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
    $this->mockedFileEntity->method('id')->willReturn(333);

    $acquiadam = $this->getMockBuilder(Acquiadam::class)
      ->disableOriginalConstructor()
      ->getMock();

    $asset_media_factory = $this->getMockBuilder(AssetMediaFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $asset_media_factory->method('getFileEntity')
      ->willReturn($this->mockedFileEntity->id());

    $this->container = new ContainerBuilder();
    $this->setMockedDrupalServices($this->container);
    $this->container->set('media_acquiadam.asset_image.helper',
      $this->getAssetImageHelperStub());
    $this->container->set('media_acquiadam.acquiadam', $acquiadam);
    $this->container->set('media_acquiadam.asset_media.factory',
      $asset_media_factory);
    $this->container->set('logger.factory', $this->getLoggerFactoryStub());
    \Drupal::setContainer($this->container);

    $this->assetFileEntityHelper = $this->getMockedAssetFileEntityHelper();
  }

  /**
   * Sets Drupal mocked services into a container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container to set mocks into.
   */
  protected function setMockedDrupalServices(ContainerBuilder $container) {
    $file_storage = $this->getMockBuilder(EntityStorageInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $file_storage->method('load')
      ->with($this->mockedFileEntity->id())
      ->willReturn($this->mockedFileEntity);

    $file_storage->method('loadByProperties')->willReturnMap([
      [
        ['uri' => 'private://assets/replaced/' . $this->getAssetData()->filename],
        [$this->mockedFileEntity],
      ],
      [
        ['uri' => 'private://assets/replaced/Micro turbine 60.jpg'],
        [$this->mockedFileEntity],
      ],
    ]);

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['file', $file_storage],
    ]);

    $data_definition = $this->getMockBuilder(DataDefinitionInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $data_definition->method('getSetting')->willReturnMap([
      ['uri_scheme', 'private'],
      ['file_directory', 'assets/[token]'],
    ]);

    $field_definition = $this->getMockBuilder(FieldDefinitionInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $field_definition->method('getItemDefinition')
      ->willReturn($data_definition);

    $entity_field_manager = $this->getMockBuilder(EntityFieldManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_field_manager->method('getFieldDefinitions')->willReturnMap([
      ['media', 'media_acquiadam', ['phpunit_file_field' => $field_definition]],
    ]);

    $token = $this->getMockBuilder(Token::class)
      ->disableOriginalConstructor()
      ->getMock();
    $token->method('replace')
      ->willReturnCallback(function ($string, $a, $b, $c) {
        return ('assets/[token]' == $string) ? 'assets/replaced' : $string;
      });

    $file_system = $this->getMockBuilder(FileSystem::class)
      ->disableOriginalConstructor()
      ->setMethods(['prepareDirectory'])
      ->getMockForAbstractClass();
    $file_system->method('prepareDirectory')->willReturnMap([
      [
        'private://assets/replaced',
        FileSystemInterface::CREATE_DIRECTORY,
        TRUE,
      ],
    ]);

    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('entity_field.manager', $entity_field_manager);
    $container->set('config.factory', $this->getConfigFactoryStub());
    $container->set('file_system', $file_system);
    $container->set('token', $token);
  }

  /**
   * Get a mocked AssetFileEntityHelper that stubs file operations.
   *
   * @return \Drupal\media_acquiadam\Service\AssetFileEntityHelper|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked AssetFileEntityHelper class.
   */
  protected function getMockedAssetFileEntityHelper() {
    $helper = $this->getMockBuilder(AssetFileEntityHelper::class)
      ->setConstructorArgs([
        $this->container->get('entity_type.manager'),
        $this->container->get('entity_field.manager'),
        $this->container->get('config.factory'),
        $this->container->get('file_system'),
        $this->container->get('token'),
        $this->container->get('media_acquiadam.asset_image.helper'),
        $this->container->get('media_acquiadam.acquiadam'),
        $this->container->get('media_acquiadam.asset_media.factory'),
        $this->container->get('logger.factory'),
      ])
      ->setMethods([
        'drupalFileSaveData',
        'phpFileGetContents',
      ])
      ->getMock();

    $helper->method('phpFileGetContents')->willReturn('File contents');
    $helper->method('drupalFileSaveData')->willReturn($this->mockedFileEntity);

    return $helper;
  }

}
