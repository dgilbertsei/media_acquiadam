<?php

namespace Drupal\media_acquiadam;

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
   * @var \Drupal\media_acquiadam\Client
   */
  protected $acquiaDamClient;

  /**
   * Acquia DAM logging service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Acquiadam constructor.
   *
   * @param \Drupal\media_acquiadam\Client $client
   *   An instance of Client that we can get a acquiadam client from.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The Drupal LoggerChannelFactory service.
   */
  public function __construct(Client $client, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->acquiaDamClient = $client;
    $this->loggerChannel = $loggerChannelFactory->get('media_acquiadam');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_acquiadam.client'),
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
  public function getAsset(string $assetId) {
    $asset = $this->staticAssetCache('get', $assetId);

    try {
      if (is_null($asset)) {
        $this->staticAssetCache(
          'set',
          $assetId,
          $this->acquiaDamClient->getAsset($assetId) ?? FALSE
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
          '%message' => $x->getMessage(),
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
   * @param string|null $assetId
   *   The asset ID when using get or set.
   * @param \Drupal\media_acquiadam\Entity\Asset|false|null $asset
   *   The data to store under the given asset ID.
   *
   * @return mixed|null
   *   The static cache or NULL if unset.
   */
  public function staticAssetCache(string $op, string $assetId = NULL, $asset = NULL) {
    if ('set' == $op) {
      return static::$cachedAssets[$assetId] = $asset;
    }
    elseif ('clear' == $op) {
      static::$cachedAssets = [];
    }

    return static::$cachedAssets[$assetId] ?? NULL;
  }

}
