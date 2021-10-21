<?php

namespace Drupal\media_acquiadam\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\media_acquiadam\Service\AssetMediaFactory;
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
   * @var \Drupal\Core\Logger\LoggerChannelInterface
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
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelInterface $loggerChannel, EntityTypeManagerInterface $entityTypeManager, AssetMediaFactory $assetMediaFactory, ConfigFactory $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerChannel = $loggerChannel;
    $this->entityTypeManager = $entityTypeManager;
    $this->assetMediaFactory = $assetMediaFactory;
    $this->configFactory = $config_factory;
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
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return bool
   *   TRUE if a media entity was updated successfully, FALSE - otherwise.
   */
  public function processItem($data) {

    if (empty($data['media_id'])) {
      return FALSE;
    }

    /** @var \Drupal\media\Entity\Media $entity */
    $entity = $this->entityTypeManager->getStorage('media')->load(
      $data['media_id']
    );
    if (empty($entity)) {
      $this->loggerChannel->error(
        'Unable to load media entity @media_id in order to refresh the associated asset. Was the media entity deleted within Drupal?',
        ['@media_id' => $data['media_id']]
      );
      return FALSE;
    }

    try {
      $assetID = $this->assetMediaFactory->get($entity)->getAssetId();
      if (empty($assetID)) {
        $this->loggerChannel->error(
          'Unable to load asset ID from media entity @media_id. This might mean that the DAM and Drupal relationship has been broken. Please check the media entity.',
          ['@media_id' => $data['media_id']]
        );
        return FALSE;
      }
      $asset = $this->assetMediaFactory->get($entity)->getAsset();
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
    if ((empty($asset) || !$asset->released_and_not_expired) && $perform_delete && !$entity->isPublished()) {
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
    if (!empty($asset) && !$asset->released_and_not_expired && $entity->isPublished()) {
      $this->loggerChannel->warning(
        'Unable to delete media entity @media_id because it is published. This warning will continue to appear until the media entity has been deleted or unpublished.',
        [
          '@media_id' => $data['media_id'],
        ]
      );
      return FALSE;
    }

    // If the asset does not exist anymore in Acquia DAM, log the information
    // for visibility.
    if (empty($asset)) {
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
      // metadata and asset file.
      $entity->save();
    }
    catch (\Exception $x) {
      // If we're hitting an exception after the above checks there might be
      // something impacting the overall system, so prevent further queue
      // processing.
      throw new SuspendQueueException($x->getMessage());
    }

    return TRUE;
  }

}
