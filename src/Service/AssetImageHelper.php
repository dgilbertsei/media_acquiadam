<?php

namespace Drupal\media_acquiadam\Service;

use Symfony\Component\Mime\MimeTypesInterface;
use cweagans\webdam\Entity\Asset;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetImageHelper.
 *
 * Abstracts out several pieces of functionality that deal with generating or
 * retrieving thumbnails for assets.
 */
class AssetImageHelper implements ContainerInjectionInterface {

  /**
   * Guzzle HTTP Client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal filesystem wrapper.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Drupal MIME type guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypesInterface
   */
  protected $mimeTypeGuesser;

  /**
   * Drupal ImageFactory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * AssetImageHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal filesystem wrapper.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Guzzle HTTP Client.
   * @param \Symfony\Component\Mime\MimeTypesInterface $mimeTypeGuesser
   *   Drupal MIME type guesser.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   Drupal ImageFactory service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem, ClientInterface $httpClient, MimeTypesInterface $mimeTypeGuesser, ImageFactory $imageFactory) {
    $this->httpClient = $httpClient;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->imageFactory = $imageFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('http_client'),
      $container->get('file.mime_type.guesser'),
      $container->get('image.factory')
    );
  }

  /**
   * Get the URL to the DAM-provided thumbnail if possible.
   *
   * @param \cweagans\webdam\Entity\Asset $asset
   *   The asset to get the thumbnail size from.
   * @param int $thumbnailSize
   *   Find the closest thumbnail size without going over when multiple
   *   thumbnails are available.
   *
   * @return string|false
   *   The preview URL or FALSE if none available.
   */
  public function getThumbnailUrlBySize(Asset $asset, $thumbnailSize = 1280) {

    if (empty($asset->thumbnailurls[0]->url)) {
      return FALSE;
    }

    // Copy thumbnail array to variable to avoid a notice about indirect
    // access.
    $thumbnails = $asset->thumbnailurls;

    // Default to first regardless of size.
    $biggest_matching = $thumbnails[0]->url;

    foreach ($thumbnails as $thumbnail) {
      if (!empty($thumbnail->url) && $thumbnailSize >= $thumbnail->size) {
        // Certain types do not have a 1280 size available despite returning
        // a URL. We either have to hard code mime types as they crop up, or
        // check if the URL is accessible on our own. Other URL sizes do not
        // appear to have this issue.
        if (1280 == $thumbnail->size && $this->checkRemoteThumbnailStatusCode(
            $thumbnail->url,
            403
          )) {
          continue;
        }
        $biggest_matching = $thumbnail->url;
      }
    }

    return $biggest_matching;
  }

  /**
   * Check if the remote URL matches the given status code.
   *
   * @param string $url
   *   The URL to perform a HEAD request against.
   * @param int $statusCode
   *   The status code to look for. Defaults to 403.
   *
   * @return bool
   *   TRUE if the status code was a match.
   */
  protected function checkRemoteThumbnailStatusCode($url, $statusCode = 403) {
    $response = $this->httpClient->head($url);
    return !empty($response) && $response->getStatusCode() == $statusCode;
  }

  /**
   * Get the thumbnail for the given asset.
   *
   * @param \cweagans\webdam\Entity\Asset $asset
   *   The Acquia DAM asset.
   * @param \Drupal\file\FileInterface|false $file
   *   The file entity to create a thumbnail uri from.
   *
   * @return string|false
   *   The image URI to use or FALSE.
   */
  public function getThumbnail(Asset $asset, $file = FALSE) {
    if (empty($file) || !$file instanceof FileInterface) {
      return $this->getFallbackThumbnail();
    }

    $mimetype = $this->getMimeTypeFromFileType($asset->filetype);
    $is_image = 'image' == $mimetype['discrete'];

    $thumbnail = $is_image ?
      $this->getImageThumbnail($file) :
      $this->getGenericMediaIcon($mimetype);

    return !empty($thumbnail) ? $thumbnail : $this->getFallbackThumbnail();
  }

  /**
   * Get MIME type information based on a file extension.
   *
   * @param string $fileType
   *   The file extension to get information for.
   *
   * @return array|false
   *   The MIME type information or FALSE on failure.
   */
  public function getMimeTypeFromFileType($fileType) {
    $fake_name = sprintf('public://nothing.%s', $fileType);
    $mimetype = $this->mimeTypeGuesser->guess($fake_name);
    if (empty($mimetype)) {
      return FALSE;
    }

    list($discrete_type, $subtype) = explode('/', $mimetype, 2);

    return [
      'discrete' => $discrete_type,
      'sub' => $subtype,
    ];
  }

  /**
   * Get a fallback image to use for the thumbnail.
   *
   * @return string
   *   The Drupal image path to use.
   */
  public function getFallbackThumbnail() {

    $fallback = $this->configFactory->get('media_acquiadam.settings')->get(
        'fallback_thumbnail'
      );

    // There was no configured fallback image, so we should use the one bundled
    // with the module.
    if (empty($fallback)) {
      // @BUG: Can default to any image named webdam.png, not necessarily ours.
      $default_scheme = $this->configFactory->get('system.file')->get(
          'default_scheme'
        );
      $fallback = sprintf('%s://webdam.png', $default_scheme);
      if (!$this->phpFileExists($fallback)) {
        $fallback = $this->setFallbackThumbnail($fallback);
      }
    }

    return $fallback;
  }

  /**
   * Determine if the given file exists.
   *
   * This call is broken out for better flexibility when writing tests.
   *
   * @param string $uri
   *   The URI to a file.
   *
   * @return bool
   *   TRUE if the given file exists.
   */
  protected function phpFileExists($uri) {
    return file_exists($uri);
  }

  /**
   * Sets a new default fallback image.
   *
   * @param string $uri
   *   The URI to use as the fallback image.
   *
   * @return string
   *   The URI that was used in case of a file rename.
   */
  protected function setFallbackThumbnail($uri) {
    // Drupal core prevents generating image styles from module directories,
    // so we need to copy our placeholder to the files directory first.
    $source = $this->getAcquiaDamModulePath() . '/img/webdam.png';
    if (!$this->phpFileExists($uri)) {
      $uri = $this->fileSystem->copy($source, $uri);
      if (!empty($uri)) {
        $this->saveFallbackThumbnail($uri);
      }
    }

    return $uri;
  }

  /**
   * Get the path to the Media: Acquia DAM module.
   *
   * This call is broken out for better flexibility when writing tests.
   *
   * @return string
   *   The path to the Media: Acquia DAM module.
   */
  protected function getAcquiaDamModulePath() {
    return \Drupal::service('extension.list.module')->getPath('media_acquiadam');
  }

  /**
   * Saves the fallback thumbnail URI to the Media: Acquia DAM config.
   *
   * This call is broken out for better flexibility when writing tests.
   *
   * @param string $uri
   *   The URI to save as the fallback thumbnail.
   */
  protected function saveFallbackThumbnail($uri) {
    $this->configFactory->getEditable('media_acquiadam.settings')->set(
        'fallback_thumbnail',
        $uri
      )->save();
  }

  /**
   * Get an image path from a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The image file to get the image path for.
   *
   * @return false|string
   *   The image path to use or FALSE on failure.
   */
  public function getImageThumbnail(FileInterface $file) {
    /** @var \Drupal\Core\Image\Image $image */
    $image = $this->imageFactory->get($file->getFileUri());

    if ($image->isValid()) {
      // Pre-create all image styles.
      $styles = ImageStyle::loadMultiple();
      foreach ($styles as $style) {
        /** @var \Drupal\image\Entity\ImageStyle $style */
        $style->flush($file->getFileUri());
      }
      return $file->getFileUri();
    }

    return FALSE;
  }

  /**
   * Gets a generic file icon based on mimetype.
   *
   * @param array $mimetype
   *   An array of a discrete type and a subtype.
   *
   * @return bool|string
   *   A path to a generic filetype icon or FALSE on failure.
   */
  public function getGenericMediaIcon(array $mimetype) {
    $icon_base = $this->configFactory->get('media.settings')->get(
        'icon_base_uri'
      );

    $generic_paths = [
      sprintf(
        '%s/%s-%s.png',
        $icon_base,
        $mimetype['discrete'],
        $mimetype['sub']
      ),
      sprintf('%s/%s.png', $icon_base, $mimetype['sub']),
      sprintf('%s/generic.png', $icon_base),
    ];

    foreach ($generic_paths as $generic_path) {
      if ($this->phpFileExists($generic_path)) {
        return $generic_path;
      }
    }

    return FALSE;
  }

}
