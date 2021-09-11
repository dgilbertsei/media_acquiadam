<?php

namespace Drupal\acquiadam;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Acquiadam.
 *
 * Abstracts away details of the REST API.
 */
class Acquiadam implements AcquiadamInterface, ContainerInjectionInterface {

  /**
   * Temporary asset data storage.
   *
   * @var array
   */
  protected static $cachedAssets = [];

  /**
   * The Acquia DAM client service.
   *
   * @var \Drupal\acquiadam\Client
   */
  protected $acquiaDamClient;

  /**
   * Media: Acquia DAM logging service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Acquiadam constructor.
   *
   * @param \Drupal\acquiadam\ClientFactory $client_factory
   *   An instance of ClientFactory that we can get a webdam client from.
   * @param string $credential_type
   *   The type of credentials to use.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   */
  public function __construct(ClientFactory $client_factory, $credential_type, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->acquiaDamClient = $client_factory->get($credential_type);
    $this->loggerChannel = $loggerChannelFactory->get('acquiadam');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquiadam.client_factory'),
      'background',
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, array $arguments) {
    $method_variable = [$this->acquiaDamClient, $name];
    return is_callable($method_variable) ?
      call_user_func_array($method_variable, $arguments) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlattenedFolderList($folder_id = NULL) {
    $folder_data = [];

    if (is_null($folder_id)) {
      $folders = $this->acquiaDamClient->getTopLevelFolders();
    }
    else {
      $folder = $this->acquiaDamClient->getFolder($folder_id);
      $folders = !empty($folder->folders) ? $folder->folders : [];
    }

    foreach ($folders as $folder) {
      $folder_data[$folder->id] = $folder->name;

      $folder_list = $this->getFlattenedFolderList($folder->id);

      foreach ($folder_list as $folder_id => $folder_name) {
        $folder_data[$folder_id] = $folder_name;
      }
    }

    return $folder_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getAsset($assetId, $include_xmp = FALSE) {

    $asset = $this->staticAssetCache('get', $assetId);

    // @BUG: XMP-less assets may bypass static caching.
    // Technically if the asset doesn't have xmp_metadata (and always returns
    // an empty value) this will bypass the cache version each call.
    $needs_xmp_get = $include_xmp && empty($asset->xmp_metadata);

    try {
      if (is_null($asset) || $needs_xmp_get) {
        $this->staticAssetCache(
          'set',
          $assetId,
          $this->acquiaDamClient->getAsset($assetId, $include_xmp) ?? FALSE
        );
      }
    }
    catch (ClientException $x) {
      // We want specific handling for 404 errors so we can provide a more
      // relateable error message.
      if (404 != $x->getCode()) {
        throw $x;
      }

      $this->loggerChannel->warning(
        'Received a missing asset response when trying to load asset @assetID. Was the asset deleted in Acquia DAM? DAM API client returned a @code exception code with the following message: %message',
        [
          '@assetID' => $assetId,
          '@code' => $x->getCode(),
          '@message' => $x->getMessage(),
        ]
      );
    }
    catch (\Exception $x) {
      $this->staticAssetCache('set', $assetId, FALSE);
      $this->loggerChannel->debug($x->getMessage());
    }

    return $this->staticAssetCache('get', $assetId);
  }

  /**
   * Static asset cache helper.
   *
   * This is a public standalone method to enable unit testing the behavior.
   *
   * @param string $op
   *   The operation to perform. One of get, set, or clear.
   * @param int $assetId
   *   The asset ID when using get or set.
   * @param \cweagans\webdam\Entity\Asset|false|null $asset
   *   The data to store under the given asset ID.
   *
   * @return mixed|null
   *   The static cache or NULL if unset.
   */
  public function staticAssetCache($op, $assetId = NULL, $asset = NULL) {
    if ('set' == $op) {
      return static::$cachedAssets[$assetId] = $asset;
    }
    elseif ('clear' == $op) {
      static::$cachedAssets = [];
    }

    return static::$cachedAssets[$assetId] ?? NULL;
  }

}
