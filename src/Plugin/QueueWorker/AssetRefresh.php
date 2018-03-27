<?php

namespace Drupal\media_acquiadam\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates Acquia DAM assets.
 *
 * @QueueWorker (
 *   id = "media_acquiadam_asset_refresh",
 *   title = @Translation("Acquia DAM Asset Refresh"),
 *   cron = {"time" = 10}
 * )
 */
class AssetRefresh extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    if (empty($data['media_id'])) {
      return;
    }

    /** @var \Drupal\media\Entity\Media $entity */
    $entity = \Drupal::entityTypeManager()
      ->getStorage('media')
      ->load($data['media_id']);
    if (empty($entity)) {
      \Drupal::logger('media_acquiadam')
        ->error('Unable to load media entity @media_id in order to refresh the associated asset. Was the item deleted?', ['@media_id' => $data['media_id']]);
      return;
    }

    try {
      // Re-save the entity, prompting the clearing and redownloading of
      // metadata and asset file.
      $entity->save();
    } catch (\Exception $x) {
      \Drupal::logger('media_acquiadam')
        ->error('Exception thrown trying to refresh asset (media: @id)', [
          '@id' => $entity->id(),
        ]);
      \watchdog_exception('media_acquiadam', $x);
      throw new SuspendQueueException($x->getMessage());
    }
  }
}
