<?php

namespace Drupal\media_acquiadam\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\media\MediaInterface;
use Drupal\media_acquiadam\Service\AssetMediaFactory;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates Acquia DAM assets.
 *
 * @QueueWorker (
 *   id = "media_acquiadam_asset_refresh",
 *   title = @Translation("Acquia DAM Asset Refresh"),
 *   cron = {"time" = 30}
 * )
 */
class AssetRefresh extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal logger channel service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * Drupal entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Acquia DAM Asset Media Factory service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetMediaFactory
   */
  protected $assetMediaFactory;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $loggerChannel, EntityTypeManagerInterface $entityTypeManager, AssetMediaFactory $assetMediaFactory, ConfigFactoryInterface $config_factory, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerChannel = $loggerChannel;
    $this->entityTypeManager = $entityTypeManager;
    $this->assetMediaFactory = $assetMediaFactory;
    $this->configFactory = $config_factory;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('media_acquiadam'),
      $container->get('entity_type.manager'),
      $container->get('media_acquiadam.asset_media.factory'),
      $container->get('config.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return bool
   *   TRUE if a media entity was updated successfully, FALSE - otherwise.
   *
   * @phpstan-param array{media_id: string} $data
   */
  public function processItem($data) {
    if (empty($data['media_id'])) {
      return FALSE;
    }

    $entity = $this->entityTypeManager->getStorage('media')->load(
      $data['media_id']
    );
    if (!$entity instanceof MediaInterface) {
      $this->loggerChannel->error(
        'Unable to load media entity @media_id in order to refresh the associated asset. Was the media entity deleted within Drupal?',
        ['@media_id' => $data['media_id']]
      );
      return FALSE;
    }

    $wrapped_media = $this->assetMediaFactory->get($entity);
    $assetID = $wrapped_media->getAssetId();
    if (empty($assetID)) {
      $this->loggerChannel->error(
        'Unable to load asset ID from media entity @media_id. This might mean that the DAM and Drupal relationship has been broken. Please check the media entity.',
        ['@media_id' => $data['media_id']]
      );
      return FALSE;
    }
    try {
      $asset = $wrapped_media->getAsset();
    }
    catch (ServerException $exception) {
      // If there is a server exception, suspend the queue.
      throw new SuspendQueueException(
        $exception->getMessage(),
        $exception->getCode(),
        $exception
      );
    }
    catch (ConnectException $exception) {
      throw new SuspendQueueException(
        'Could not create connection to DAM, possible local network issue',
        $exception->getCode(),
        $exception
      );
    }
    catch (ClientException $exception) {
      $response = $exception->getResponse();
      if ($response === NULL) {
        throw new RequeueException();
      }
      if ($response->getStatusCode() === 401) {
        throw new SuspendQueueException('Unable to process queue due to authorization errors', 401, $exception);
      }
      // If 404 response, the asset has been removed. Allow processing to
      // determine if it should be deleted.
      if ($response->getStatusCode() === 404) {
        $asset = NULL;
      }
      elseif ($response->getStatusCode() === 408) {
        throw new DelayedRequeueException(60, 'Timed out loading asset, trying again later.', 408, $exception);
      }
      else {
        // Try again for unknown 4xx responses.
        throw new RequeueException();
      }
    }
    catch (\Exception $x) {
      $this->loggerChannel->error(
        'Error trying to check asset from media entity @media_id',
        ['@media_id' => $data['media_id']]
      );
      return FALSE;
    }

    // If the asset is expired/deleted in Acquia DAM and is unpublished in
    // Drupal, we delete it.
    $perform_delete = $this->configFactory->get('media_acquiadam.settings')->get('sync_perform_delete');
    if (($asset === NULL || !$asset->released_and_not_expired) && $perform_delete && !$entity->isPublished()) {
      $entity->delete();
      $this->loggerChannel->warning(
        'Deleted media entity @media_id with asset id @assetID.',
        [
          '@media_id' => $data['media_id'],
          '@assetID' => $assetID,
        ]
      );
      return TRUE;
    }

    // If the asset is expired/deleted in Acquia DAM but is published in
    // Drupal, we log it because we can't delete it.
    if ($asset !== NULL && !$asset->released_and_not_expired && $entity->isPublished()) {
      $this->loggerChannel->warning(
        'Unable to delete media entity @media_id because it is published. This warning will continue to appear until the media entity has been deleted or unpublished.',
        [
          '@media_id' => $data['media_id'],
        ]
      );
    }

    // If the asset does not exist anymore in Acquia DAM, log the information
    // for visibility.
    if ($asset === NULL) {
      $this->loggerChannel->warning(
        'Unable to update media entity @media_id with information from asset @assetID because the asset was missing. This warning will continue to appear until the media entity has been deleted.',
        [
          '@media_id' => $data['media_id'],
          '@assetID' => $assetID,
        ]
      );
      return FALSE;
    }

    try {
      // Re-save the entity, prompting the clearing and redownloading of
      // metadata and asset file. We always modify the changed time to help
      // indicate the last time this media entity had a refresh performed.
      $entity->setChangedTime($this->time->getCurrentTime());
      $entity->save();
    }
    catch (\Exception $x) {
      // Something within `media_acquiadam_media_presave` caused a failure that
      // we cannot detect. Provide verbose logging and let its lock expire
      // before re-processing.
      $this->loggerChannel->error(
        'Error when saving media to sync data with DAM for media entity @media_id and asset ID @asset_id. Leaving in queue for retry.

%exception_type: %error',
        [
          '@media_id' => $data['media_id'],
          '@asset_id' => $assetID,
          '%exception_type' => get_class($x),
          '%error' => $x->getMessage(),
        ]
      );
      return FALSE;
    }

    return TRUE;
  }

}
