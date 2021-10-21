<?php

namespace Drupal\Tests\media_acquiadam\Traits;

use Drupal\Core\Field\FieldDefinitionInterface;
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

    $media_bundle = $this->getMockBuilder(MediaTypeInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $bundle_definition = $this->getMockBuilder(EntityReferenceFieldItemListInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $bundle_definition->entity = $media_bundle;

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

    $file_field = $this->getMockBuilder(\stdClass::class)
      ->disableOriginalConstructor()
      ->setMethods(['first', 'mainPropertyName'])
      ->getMock();
    $file_field->method('first')->willReturnSelf();
    $file_field->method('mainPropertyName')->willReturn('target_id');

    $media->phpunit_file_field = $file_field;
    $media->phpunit_file_field->target_id = $this->getMockedFileEntity()->id();

    $asset_id_field = $this->getMockBuilder(\stdClass::class)
      ->disableOriginalConstructor()
      ->setMethods(['first', 'mainPropertyName'])
      ->getMock();
    $asset_id_field->method('first')->willReturnSelf();
    $asset_id_field->method('mainPropertyName')->willReturn('value');

    $media->phpunit_asset_id_field = $asset_id_field;
    $media->phpunit_asset_id_field->value = $assetId;

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
