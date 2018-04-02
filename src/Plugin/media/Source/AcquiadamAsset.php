<?php

namespace Drupal\media_acquiadam\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\media_acquiadam\AssetDataInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Array of DAM XMP fields keyed by field (prefixed with "xmp_").
   *
   * @var array
   */
  protected $acquiadamXmpFields;

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
   * The asset data service.
   * @var \Drupal\media_acquiadam\AssetData
   */
  protected $asset_data;

  /**
   * AcquiadamAsset constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, Token $token, AcquiadamInterface $acquiadam, AssetDataInterface $asset_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);

    $this->token = $token;
    $this->acquiadam = $acquiadam;
    $this->acquiadamXmpFields = $this->acquiadam->getActiveXmpFields();
    $this->asset_data = $asset_data;
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
      $container->get('media_acquiadam.acquiadam'),
      $container->get('media_acquiadam.asset_data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    // The asset properties.
    // @TODO: Determine if other properties need to be added here.
    $fields = [
      'colorspace' => $this->t('Color space'),
      'datecaptured' => $this->t('Date captured'),
      'datecreated' => $this->t('Date created'),
      'datemodified' => $this->t('Date modified'),
      'description' => $this->t('Description'),
      'file' => $this->t('File'),
      'filename' => $this->t('Filename'),
      'filesize' => $this->t('Filesize'),
      'filetype' => $this->t('Filetype'),
      'folderID' => $this->t('Folder ID'),
      'height' => $this->t('Height'),
      'status' => $this->t('Active state'),
      'type_id' => $this->t('Type ID'),
      'version' => $this->t('Version'),
      'width' => $this->t('Width'),
    ];

    // Add additional XMP fields to fields array.
    foreach($this->acquiadamXmpFields as $xmp_id => $xmp_field) {
      $fields[$xmp_id] = $xmp_field['label'];
    }

    return $fields;
  }

  /**
   * Get the asset ID for the given media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to pull the asset ID from.
   *
   * @return integer|bool
   *   The asset ID or FALSE on failure.
   */
  public function getAssetID(MediaInterface $media) {
    if (isset($this->configuration['source_field'])) {
      $source_field = $this->configuration['source_field'];

      if ($media->hasField($source_field)) {
        $property_name = $media->{$source_field}->first()->mainPropertyName();
        return $media->{$source_field}->{$property_name};
      }
    }
    return FALSE;
  }

  /**
   * Retrieve an asset from Acquia DAM.
   *
   * @param integer $assetID
   *   The ID of the asset to retrieve.
   * @param bool $includeXMP
   *   TRUE to include XMP metadata.
   *
   * @return bool|\cweagans\webdam\Entity\Asset
   */
  public function getAsset($assetID, $includeXMP = FALSE) {
    // Temporarily cache loaded assets to handle multiple save calls in a
    // single request.
    $assets = &\drupal_static('AcquiaDAMAsset::getAsset', []);
    try {
      $needs_first_get = !isset($assets[$assetID]);
      // @BUG: XMP-less assets may bypass static caching.
      // Technically if the asset doesn't have xmp_metadata (and always
      // returns an empty value) this will bypass the cache version each call.
      $needs_xmp_get = $includeXMP && empty($assets[$assetID]->xmp_metadata);
      if ($needs_first_get || $needs_xmp_get) {
        $assets[$assetID] = $this->acquiadam->getAsset($assetID, $includeXMP);
      }
    } catch (ClientException $x) {
      // We want specific handling for 404 errors so we can provide a more
      // relateable error message.
      if (404 == $x->getCode()) {
        \Drupal::logger('media_acquiadam')
          ->warning('Received a missing asset response when trying to load asset @assetID. Was the asset deleted in Acquia DAM?', ['@assetID' => $assetID]);

        // In the event of a 404 we assume the asset has been deleted within
        // Acquia DAM and need to save that state for excluding it from cron
        // syncs in the future.
        $this->asset_data->set($assetID, 'remote_deleted', TRUE);
      }
      else {
        watchdog_exception('media_acquiadam', $x);
      }
    } catch (\Exception $x) {
      watchdog_exception('media_acquiadam', $x);
    }
    finally {
      if (!isset($assets[$assetID])) {
        $assets[$assetID] = FALSE;
      }
    }

    return $assets[$assetID];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    $assetID = $this->getAssetID($media);
    if (empty($assetID)) {
      return NULL;
    }

    if (empty($this->asset)) {
      $asset = $this->getAsset($assetID, TRUE);
      if (empty($asset)) {
        return NULL;
      }
      $this->asset = $asset;
    }

    // Return values of XMP metadata.
    if (array_key_exists($name, $this->acquiadamXmpFields)) {
      // Strip 'xmp_' prefix to retrieve matching asset xmp metadata.
      $xmp_field = substr($name, 4);
      if (isset($this->asset->xmp_metadata[$xmp_field]['value'])) {
        return $this->asset->xmp_metadata[$xmp_field]['value'];
      }
      else {
        return NULL;
      }
    }

    switch ($name) {
      case 'default_name':
        return parent::getMetadata($media, 'default_name');

      case 'thumbnail_uri':
        return $this->thumbnail($media);

      case 'folderID':
        return isset($this->asset->folder->id) ? $this->asset->folder->id : NULL;

      case 'file':
        return $this->file ? $this->file->id() : NULL;

      case 'status':
        return isset($this->asset->status) ? intval($this->asset->status == 'active') : NULL;

      default:
        // The key should be the local property name and the value should be the
        // DAM provided property name.
        $property_name_mapping = [
          'colorspace' => 'colorspace',
          'datecaptured' => 'datecapturedUnix',
          'datecreated' => 'date_created_unix',
          'datemodified' => 'date_modified_unix',
          'description' => 'description',
          'filename' => 'filename',
          'filesize' => 'filesize',
          'filetype' => 'filetype',
          'height' => 'height',
          'type_id' => 'type_id',
          'version' => 'version',
          'width' => 'width',
        ];
        if (in_array($name, $property_name_mapping)) {
          $property_name = $property_name_mapping[$name];
          return isset($this->asset->{$property_name}) ? $this->asset->{$property_name} : NULL;
        }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function thumbnail(MediaInterface $media) {
    $assetID = $this->getAssetID($media);
    if (empty($assetID)) {
      return FALSE;
    }

    $asset = $this->getAsset($assetID);
    if (empty($asset)) {
      return FALSE;
    }

    // Download the asset file as a string.
    $file_contents = $this->acquiadam->downloadAsset($asset->id);
    // Set the path for assets.
    // Load the bundle for this asset.
    $bundle = $this->entityTypeManager->getStorage('media_type')
      ->load($media->bundle());
    // If the bundle has a field mapped for the file define it.
    $field_map = $bundle->getFieldMap();
    $file_field = isset($field_map['file']) ? $field_map['file'] : '';
    // Define path.
    $scheme = 'public';
    // Define file directory.
    $file_directory = 'acquiadam_assets/';
    if ($file_field) {
      // Load the field definitions for this bundle.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($media->getEntityTypeId(), $media->bundle());
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
