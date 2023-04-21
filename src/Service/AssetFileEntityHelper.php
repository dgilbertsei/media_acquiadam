<?php

namespace Drupal\media_acquiadam\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\media_acquiadam\Entity\Asset;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetFileEntityHelper.
 *
 * Abstracts out primarily file entity and system file related functionality.
 */
class AssetFileEntityHelper implements ContainerInjectionInterface {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Field Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Acquia DAM config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Drupal filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Drupal token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Acquia DAM asset image helper service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetImageHelper
   */
  protected $assetImageHelper;

  /**
   * Acquia DAM client.
   *
   * @var \Drupal\media_acquiadam\AcquiadamInterface
   */
  protected $acquiaDamClient;

  /**
   * Acquia DAM factory for wrapping media entities.
   *
   * @var \Drupal\media_acquiadam\Service\AssetMediaFactory
   */
  protected $assetMediaFactory;

  /**
   * Acquia DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The file repository.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * AssetFileEntityHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field Manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal filesystem service.
   * @param \Drupal\Core\Utility\Token $token
   *   Drupal token service.
   * @param \Drupal\media_acquiadam\Service\AssetImageHelper $assetImageHelper
   *   Acquia DAM asset image helper service.
   * @param \Drupal\media_acquiadam\AcquiadamInterface $acquiaDamClient
   *   Acquia DAM client.
   * @param \Drupal\media_acquiadam\Service\AssetMediaFactory $assetMediaFactory
   *   Acquia DAM Asset Media Factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   * @param \GuzzleHttp\Client $client
   *   The HTTP client.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    Token $token,
    AssetImageHelper $assetImageHelper,
    AcquiadamInterface $acquiaDamClient,
    AssetMediaFactory $assetMediaFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    Client $client) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('media_acquiadam.settings');
    $this->fileSystem = $fileSystem;
    $this->token = $token;
    $this->assetImageHelper = $assetImageHelper;
    $this->acquiaDamClient = $acquiaDamClient;
    $this->assetMediaFactory = $assetMediaFactory;
    $this->loggerChannel = $loggerChannelFactory->get('media_acquiadam');
    $this->httpClient = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('token'),
      $container->get('media_acquiadam.asset_image.helper'),
      $container->get('media_acquiadam.acquiadam'),
      $container->get('media_acquiadam.asset_media.factory'),
      $container->get('logger.factory'),
      $container->get('http_client')
    );
    if ($container->has('file.repository')) {
      $instance->setFileRepository($container->get('file.repository'));
    }
    return $instance;
  }

  /**
   * Sets the file repository service, introduced in 9.3.
   *
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository.
   */
  public function setFileRepository(FileRepositoryInterface $file_repository) {
    $this->fileRepository = $file_repository;
  }

  /**
   * Get a destination uri from the given entity and field combo.
   *
   * Following will be concatenated to create the path:
   *  - scheme
   *  - acquiadam_assets
   *  - Upload year from DAM
   *  - Upload month from DAM.
   *
   * Example: public://acquiadam_assets/2022-12
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check the field configuration on.
   * @param string $fileField
   *   The name of the file field.
   * @param string $upload_date
   *   Upload date of the asset from DAM (ISO8601).
   *
   * @return string
   *   The uri to use.
   */
  public function getDestinationFromEntity(EntityInterface $entity, string $fileField, string $upload_date): string {
    $scheme = $this->configFactory->get('system.file')->get('default_scheme');
    $file_directory = 'acquiadam_assets/[date:custom:Y]-[date:custom:m]';

    // Load the field definitions for this bundle.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );

    if (!empty($field_definitions[$fileField])) {
      $definition = $field_definitions[$fileField]->getItemDefinition();
      $scheme = $definition->getSetting('uri_scheme');
      $file_directory = $definition->getSetting('file_directory');
    }

    // Replace the token for file directory.
    if (!empty($file_directory)) {
      $file_directory = $this->token->replace($file_directory);
    }

    return sprintf('%s://%s', $scheme, $file_directory);
  }

  /**
   * Creates a new file for an asset.
   *
   * @param \Drupal\media_acquiadam\Entity\Asset $asset
   *   The asset to save a new file for.
   * @param string $destination_folder
   *   The path to save the asset into.
   *
   * @return bool|\Drupal\file\FileInterface
   *   The created file or FALSE on failure.
   */
  public function createNewFile(Asset $asset, $destination_folder) {
    // By default, we use the filename attribute as the file name. However,
    // because the actual file format may differ than the file name (specially
    // for the images which are downloaded as png), we pass the filename
    // as a parameter so it can be overridden.
    $filename = $asset->filename;
    $file_contents = $this->fetchRemoteAssetData($asset, $filename);
    if ($file_contents === FALSE) {
      return FALSE;
    }

    $file = $this->assetMediaFactory->getFileEntity($asset->id);
    if ($file instanceof FileInterface) {
      $uri = $this->fileSystem->saveData($file_contents, $file->getFileUri(), FileSystemInterface::EXISTS_REPLACE);
      $file->setFileUri($uri);
      $file->setFilename($filename);
      $file->save();
    }
    else {
      // Ensure we can write to our destination directory.
      if (!$this->fileSystem->prepareDirectory($destination_folder, FileSystemInterface::CREATE_DIRECTORY)) {
        $this->loggerChannel->warning(
          'Unable to save file for asset ID @asset_id on directory @destination_folder.', [
            '@asset_id' => $asset->id,
            '@destination_folder' => $destination_folder,
          ]
        );
        return FALSE;
      }
      $destination_path = sprintf('%s/%s', $destination_folder, $filename);
      $file = $this->drupalFileSaveData($file_contents, $destination_path);
    }

    if ($file instanceof FileInterface) {
      return $file;
    }

    $this->loggerChannel->warning(
      'Unable to save file for asset ID @asset_id.', [
        '@asset_id' => $asset->id,
      ]
    );

    return FALSE;
  }

  /**
   * Fetches binary asset data from a remote source.
   *
   * @param \Drupal\media_acquiadam\Entity\Asset $asset
   *   The asset to fetch data for.
   * @param string $filename
   *   The filename as a reference so it can be overridden.
   *
   * @return false|string
   *   The remote asset contents or FALSE on failure.
   */
  protected function fetchRemoteAssetData(Asset $asset, &$filename) {
    if ($this->config->get('transcode') === 'original') {
      $download_url = $asset->links->download;
    }
    elseif ($asset->file_properties->format_type === 'image') {
      // If the module was configured to enforce an image size limit then we
      // need to grab the nearest matching pre-created size.
      $size_limit = $this->config->get('size_limit');

      $download_url = $this->assetImageHelper->getThumbnailUrlBySize($asset, $size_limit);

      if (empty($download_url)) {
        $this->loggerChannel->warning(
          'Unable to save file for asset ID @asset_id. Thumbnail for request size (@size px) has not been found.', [
            '@asset_id' => $asset->id,
            '@size' => $size_limit,
          ]
        );
        return FALSE;
      }
    }
    else {
      $original_url = $asset->embeds->original->url ?? '';
      if ($original_url === '') {
        return FALSE;
      }
      $download_url = str_replace('&download=true', '', $original_url);
    }

    try {
      $response = $this->httpClient->get($download_url, [
        'allow_redirects' => [
          'track_redirects' => TRUE,
        ],
      ]);
      $size = $response->getBody()->getSize();
      if ($size === NULL || $size === 0) {
        $this->loggerChannel->error('Unable to download contents for asset ID @asset_id. Received zero-byte response for download URL @url with redirects to @history',
        [
          '@asset_id' => $asset->id,
          '@url' => $download_url,
          '@history' => $response->getHeaderLine('X-Guzzle-Redirect-History'),
        ]);
        return FALSE;
      }
      $file_contents = (string) $response->getBody();
      if ($response->hasHeader('Content-Disposition')) {
        $disposition = $response->getHeader('Content-Disposition')[0];
        preg_match('/filename="(.*)"/', $disposition, $matches);
        if (count($matches) > 1) {
          $filename = $matches[1];
        }
      }
    }
    catch (RequestException $exception) {
      $message = 'Unable to download contents for asset ID @asset_id: %message. Attempted download URL @url with redirects to @history';
      $context = [
        '@asset_id' => $asset->id,
        '%message' => $exception->getMessage(),
        '@url' => $download_url,
        '@history' => '[empty request, cannot determine redirects]',
      ];
      $response = $exception->getResponse();
      if ($response) {
        $context['@history'] = $response->getHeaderLine('X-Guzzle-Redirect-History');
      }
      $this->loggerChannel->error($message, $context);
      return FALSE;
    }

    return $file_contents;
  }

  /**
   * Gets a new filename for an asset based on the URL returned by the DAM.
   *
   * @param string $destination
   *   The destination folder the asset is being saved to.
   * @param string $uri
   *   The URI that was returned by the DAM API for the asset.
   * @param string $original_name
   *   The original asset filename.
   *
   * @return string
   *   The updated destination path with the new filename.
   */
  protected function getNewDestinationByUri($destination, $uri, $original_name) {
    $path = parse_url($uri, PHP_URL_PATH);
    $path = basename($path);
    $ext = pathinfo($path, PATHINFO_EXTENSION);

    $base_file_name = pathinfo($original_name, PATHINFO_FILENAME);
    return sprintf('%s/%s.%s', $destination, $base_file_name, $ext);
  }

  /**
   * Saves a file to the specified destination and creates a database entry.
   *
   * This method exists so the functionality can be overridden in unit tests.
   *
   * @param string $data
   *   A string containing the contents of the file.
   * @param string|null $destination
   *   (optional) A string containing the destination URI. This must be a stream
   *   wrapper URI. If no value or NULL is provided, a randomized name will be
   *   generated and the file will be saved using Drupal's default files scheme,
   *   usually "public://".
   *
   * @return \Drupal\file\FileInterface|false
   *   A file entity, or FALSE on error.
   */
  protected function drupalFileSaveData($data, $destination = NULL) {
    if ($this->fileRepository instanceof FileRepositoryInterface) {
      return $this->fileRepository->writeData($data, $destination, FileSystemInterface::EXISTS_REPLACE);
    }
    return file_save_data($data, $destination, FileSystemInterface::EXISTS_REPLACE);
  }

}
