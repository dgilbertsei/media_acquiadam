<?php

namespace Drupal\media_acquiadam\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media_acquiadam\AcquiadamInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

/**
 * Provides media type plugin for Acquia DAM assets.
 *
 * @MediaSource(
 *   id = "acquiadam_asset",
 *   label = @Translation("Acquia DAM asset"),
 *   description = @Translation("Provides business logic and metadata for assets stored on Acquia DAM."),
 *   allowed_field_types = {"integer"},
 * )
 */
class AcquiadamAsset extends MediaSourceBase {

  /**
   * A configured API object.
   *
   * @var \Drupal\media_acquiadam\Acquiadam
   */
  protected $acquiadam;

  /**
   * The asset that we're going to render details for.
   *
   * @var \cweagans\webdam\Entity\Asset
   */
  protected $asset = NULL;

  /**
   * The file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $file = NULL;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * AcquiadamAsset constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, Token $token, AcquiadamInterface $acquiadam) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);

    $this->token = $token;
    $this->acquiadam = $acquiadam;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('token'),
      $container->get('media_acquiadam.acquiadam')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    // @TODO: Determine if other properties need to be added here.
    // @TODO: Determine how to support custom metadata.
    $fields = [
      'file' => $this->t('File'),
      'type_id' => $this->t('Type ID'),
      'filename' => $this->t('Filename'),
      'filesize' => $this->t('Filesize'),
      'width' => $this->t('Width'),
      'height' => $this->t('Height'),
      'description' => $this->t('Description'),
      'filetype' => $this->t('Filetype'),
      'colorspace' => $this->t('Color space'),
      'version' => $this->t('Version'),
      'datecreated' => $this->t('Date created'),
      'datemodified' => $this->t('Date modified'),
      'datecaptured' => $this->t('Date captured'),
      'folderID' => $this->t('Folder ID'),
      'status' => $this->t('Active state'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    $assetID = NULL;
    if (isset($this->configuration['source_field'])) {
      $source_field = $this->configuration['source_field'];

      if ($media->hasField($source_field)) {
        $property_name = $media->{$source_field}->first()->mainPropertyName();
        $assetID = $media->{$source_field}->{$property_name};
      }
    }
    // If we don't have an asset ID, there's not much we can do.
    if (is_null($assetID)) {
      return FALSE;
    }
    // If the asset has not been loaded.
    if (!$this->asset) {
      // Load the asset.
      $this->asset = $this->acquiadam->getAsset($assetID);
    }
    switch ($name) {
      case 'default_name':
        return parent::getMetadata($media, 'default_name');

      case 'thumbnail_uri':
        return $this->thumbnail($media);

      case 'type_id':
        return $this->asset->type_id;

      case 'filename':
        return $this->asset->filename;

      case 'filesize':
        return $this->asset->filesize;

      case 'width':
        return $this->asset->width;

      case 'height':
        return $this->asset->height;

      case 'description':
        return $this->asset->description;

      case 'filetype':
        return $this->asset->filetype;

      case 'colorspace':
        return $this->asset->colorspace;

      case 'version':
        return $this->asset->version;

      case 'datecreated':
        return $this->asset->date_created_unix;

      case 'datemodified':
        return $this->asset->date_modified_unix;

      case 'datecaptured':
        return $this->asset->datecapturedUnix;

      case 'folderID':
        return $this->asset->folder->id;

      case 'file':
        return $this->file ? $this->file->id() : NULL;

      case 'status':
        return intval($this->asset->status == 'active');
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function thumbnail(MediaInterface $media) {
    // Load the bundle for this asset.
    $bundle = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    // Load the field definitions for this bundle.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($media->getEntityTypeId(), $media->bundle());
    // If a source field is set for this bundle.
    if (isset($this->configuration['source_field'])) {
      // Set the name of the source field.
      $source_field = $this->configuration['source_field'];
      // If the media entity has the source field.
      if ($media->hasField($source_field)) {
        // Set the property name for the source field.
        $property_name = $media->{$source_field}->first()->mainPropertyName();
        // Get the asset ID value from the source field.
        $assetID = $media->{$source_field}->{$property_name};
      }
    }
    // If we don't have an asset ID, there's not much we can do.
    if (is_null($assetID)) {
      return FALSE;
    }
    // Load the asset.
    $asset = $this->acquiadam->getAsset($assetID);
    // Download the asset file as a string.
    $file_contents = $this->acquiadam->downloadAsset($asset->id);
    // Set the path for assets.
    // If the bundle has a field mapped for the file define it.
    $field_map = $bundle->getFieldMap();
    $file_field = isset($field_map['file']) ? $field_map['file'] : '';
    // Define path.
    $scheme = 'public';
    // Define file directory.
    $file_directory = 'acquiadam_assets/';
    if ($file_field) {
      // Get the storage scheme for the file field.
      $scheme = $field_definitions[$file_field]->getItemDefinition()->getSetting('uri_scheme');
      // Get the file directory for the file field.
      $file_directory = $field_definitions[$file_field]->getItemDefinition()->getSetting('file_directory');
      // Replace the token for file directory.
      if (!empty($file_directory)) {
        $file_directory = $this->token->replace($file_directory) . '/';
      }
    }
    // Set the path prefix for the file that is about to be downloaded
    // and saved in to Drupal.
    $path = $scheme . '://' . $file_directory;
    // Prepare acquiadam directory for writing and only proceed if successful.
    if (file_prepare_directory($path, FILE_CREATE_DIRECTORY)) {
      // Save the file into Drupal.
      $file = file_save_data($file_contents, $path . $asset->id . '.' . $asset->filetype, FILE_EXISTS_REPLACE);
      // If the file was saved.
      if ($file instanceof FileInterface || $file instanceof File) {
        $this->file = $file;
        // Get the mimetype of the file.
        $mimetype = $file->getMimeType();
        // Split the mimetype into 2 parts (primary/secondary)
        $mimetype = explode('/', $mimetype);
        // If the primary mimetype is not an image.
        if ($mimetype[0] != 'image') {
          $icon_base = $this->configFactory->get('media.settings')->get('icon_base_uri');
          // Try to get the filetype icon using primary and secondary mimetype.
          $thumbnail = $icon_base . "/{$mimetype[0]}-{$mimetype[1]}.png";
          // If icon is not found.
          if (!is_file($thumbnail)) {
            // Try to get the filetype icon using only the secondary mimetype.
            $thumbnail = $icon_base . "/{$mimetype[1]}.png";
            // If icon is still not found.
            if (!is_file($thumbnail)) {
              // Use a generic document icon.
              $thumbnail = $icon_base . '/generic.png';
            }
          }
        }
        else {
          // Load the image.
          $image = \Drupal::service('image.factory')->get($file->getFileUri());
          /** @var \Drupal\Core\Image\Image $image */
          // If the image is valid.
          if ($image->isValid()) {
            // Load all image styles.
            $styles = ImageStyle::loadMultiple();
            // For each image style.
            foreach ($styles as $style) {
              /** @var \Drupal\image\Entity\ImageStyle $style */
              // Flush and regenerate the styled image.
              $style->flush($file->getFileUri());
            }
          }
          // Use the URI of the image.
          $thumbnail = $file->getFileUri();
        }
        // Return the file URI.
        return $thumbnail;
      }
    }
    return $this->getFallbackThumbnail();
  }

  /**
   * Get a fallback image to use for the thumbnail.
   *
   * @return string|FALSE
   *   The Drupal image path to use or FALSE.
   */
  protected function getFallbackThumbnail() {

    /** @var \Drupal\Core\Config\Config $config */
    $config = \Drupal::configFactory()
      ->getEditable('media_acquiadam.settings');

    $fallback = $config->get('fallback_thumbnail');
    if (empty($fallback)) {
      // There was no configured fallback image, so we should use the one
      // bundled with the module. Drupal core prevents generating image styles
      // from module directories, so we need to copy our placeholder to the
      // files directory first.
      $source = drupal_get_path('module', 'media_acquiadam') . '/img/webdam.png';

      // @TODO: Technically this will default to any image named webdam.png, not
      // necessarily the one we put there.
      $fallback = sprintf('%s://webdam.png', file_default_scheme());
      if (!file_exists($fallback)) {
        $fallback = file_unmanaged_copy($source, $fallback);
        if (!empty($fallback)) {
          $config->set('fallback_thumbnail', $fallback)->save();
        }
      }
    }

    return $fallback;
  }

}
