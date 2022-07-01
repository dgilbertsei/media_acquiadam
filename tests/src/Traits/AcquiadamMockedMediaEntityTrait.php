<?php

namespace Drupal\Tests\media_acquiadam\Traits;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
use Drupal\media\MediaTypeInterface;

/**
 * Shared complex mocked media and related entities.
 */
trait AcquiadamMockedMediaEntityTrait {

  /**
   * Mocks a Media entity to add shared functionality for tests.
   *
   * @param int $assetId
   *   The asset ID to assign to the entity.
   * @param string $sourceField
   *   The source field name to use. Allows overriding the success path.
   * @param int $mediaEntityId
   *   The ID to assign to the media entity.
   *
   * @return \Drupal\media\MediaInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked MediaInterface entity.
   */
  protected function getMockedMediaEntity($assetId, $sourceField = NULL, $mediaEntityId = 47247625) {
    if (!\Drupal::hasContainer()) {
      $container = new ContainerBuilder();
    }
    else {
      $container = \Drupal::getContainer();
    }
    $typed_data_manager = $this->createMock(TypedDataManagerInterface::class);
    $typed_data_manager
      ->method('getPropertyInstance')
      ->willReturnCallback(function () {
        $args = func_get_args();
        self::assertCount(3, $args);
        if ($args[1] === 'entity') {
          $instance = new EntityReference(
            $this->createStub(DataDefinitionInterface::class)
          );
          $instance->setValue($args[2]);
          return $instance;
        }
      });
    $container->set('typed_data_manager', $typed_data_manager);
    \Drupal::setContainer($container);

    $sourceField = $sourceField ?? 'phpunit_asset_id_field';

    $source_field_definition = $this->getMockBuilder(FieldDefinitionInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $source_field_definition->method('getName')
      ->willReturn($sourceField);

    $media_source = $this->getMockBuilder(MediaSourceInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $media_source->method('getSourceFieldDefinition')
      ->willReturn($source_field_definition);

    $media_bundle = $this->createStub(MediaTypeInterface::class);
    $media_bundle->method('id')->willReturn('test_media_type');
    $media_bundle->method('getTypedData')->willReturn(EntityAdapter::createFromEntity($media_bundle));
    $bundle_definition = $this->createStub(EntityReferenceFieldItemListInterface::class);
    $definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $definition->method('getPropertyDefinitions')->willReturn([]);
    $bundle_definition_item = new EntityReferenceItem($definition);
    $bundle_definition_item->setValue([
      'target_id' => $media_bundle->id(),
      'entity' => $media_bundle,
    ]);
    $bundle_definition->method('first')->willReturn($bundle_definition_item);
    $bundle_definition->method('__get')->with('entity')->willReturn($media_bundle);

    $media = $this->getMockBuilder(MediaInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $media->method('getSource')->willReturn($media_source);
    $media->method('uuid')->willReturn('e63ce44d-4cfe-44d4-af7d-0692821d52cc');

    $media->method('hasField')->willReturnMap([
      ['phpunit_asset_id_field', TRUE],
      ['phpunit_file_field', TRUE],
      ['phpunit_test_fail', FALSE],
    ]);
    $media->method('getEntityTypeId')->willReturn('media');
    $media->method('bundle')->willReturn('media_acquiadam');
    $media->method('id')->willReturn($mediaEntityId);
    $media->method('get')
      ->with('bundle')
      ->willReturn($bundle_definition);

    $file_field = $this->createStub(FieldItemList::class);
    $definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $definition->method('getPropertyDefinitions')->willReturn([]);
    $field_field_item = new EntityReferenceItem($definition);
    $field_field_item->setValue([
      'target_id' => $this->getMockedFileEntity()->id(),
    ]);
    $file_field->method('first')->willReturn($field_field_item);
    $media->phpunit_file_field = $file_field;
    $media->phpunit_file_field->target_id = $this->getMockedFileEntity()->id();

    $asset_id_field = $this->createStub(FieldItemListInterface::class);
    $definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $definition->method('getPropertyDefinitions')->willReturn([]);
    $asset_id_field_item = new StringItem($definition);
    $asset_id_field_item->setValue([
      'value' => $assetId,
    ]);
    $asset_id_field->method('first')->willReturn($asset_id_field_item);

    $media->phpunit_asset_id_field = $asset_id_field;

    return $media;
  }

  /**
   * Return a consistent, barebones file entity.
   *
   * @return \Drupal\file\FileInterface|\PHPUnit\Framework\MockObject\MockObject
   *   A mocked file entity.
   */
  protected function getMockedFileEntity() {
    $file_entity = $this->getMockBuilder(FileInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $file_entity->method('id')->willReturn(894782578);

    return $file_entity;
  }

}
