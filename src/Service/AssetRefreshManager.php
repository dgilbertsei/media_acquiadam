<?php

namespace Drupal\media_acquiadam\Service;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\media_acquiadam\Exception\InvalidCredentialsException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetRefreshManager.
 *
 * Adds media items to the asset sync queue for later processing.
 * Uses the Search API to get affected asset ids - determines which
 * assets where changed within the given period of time, and adds them to the
 * queue.
 *
 * @package Drupal\media_acquiadam
 */
class AssetRefreshManager implements AssetRefreshManagerInterface, ContainerInjectionInterface {

  /**
   * The Acquiadam Service.
   *
   * @var \Drupal\media_acquiadam\AcquiadamInterface
   */
  protected $acquiadam;

  /**
   * The Drupal State Service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Logger Factory Service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Queue Worker.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The media storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * The maximum number of items to return in search API response.
   *
   * @var int
   */
  protected $requestLimit = 100;

  /**
   * AssetRefreshManager constructor.
   *
   * @param \Drupal\media_acquiadam\AcquiadamInterface $acquiadam
   *   The Acquiadam Service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal State Service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The Logger Factory Service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The Queue Factory Service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AcquiadamInterface $acquiadam, StateInterface $state, LoggerChannelFactoryInterface $logger_factory, QueueFactory $queue_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->acquiadam = $acquiadam;
    $this->state = $state;
    $this->logger = $logger_factory->get('media_acquiadam');
    $this->queue = $queue_factory->get($this->getQueueName());
    $this->mediaStorage = $entity_type_manager->getStorage('media');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_acquiadam.acquiadam'),
      $container->get('state'),
      $container->get('logger.factory'),
      $container->get('queue'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQueueName(): string {
    return 'media_acquiadam_asset_refresh';
  }

  /**
   * {@inheritdoc}
   */
  public function updateQueue(array $asset_id_fields) {
    if (empty($asset_id_fields)) {
      // Nothing to process. Associated media bundles are not found.
      return 0;
    }

    // Get ids of the changed (updated/deleted) assets.
    $asset_ids = $this->getAssetIds();
    if (!$asset_ids) {
      // Nothing to process.
      return 0;
    }

    // From the list of assets which have been updated in Acquia DAM, find the
    // ones which are used into Drupal as media entities.
    $total = 0;
    $media_ids = [];
    foreach ($asset_id_fields as $bundle => $field) {
      $media_query = $this->mediaStorage->getQuery();
      $media_ids_partial = $media_query
        ->condition('bundle', $bundle)
        ->condition($field, $asset_ids, 'IN')
        ->execute();

      foreach ($media_ids_partial as $media_id) {
        $media_ids[] = $media_id;
      }
    }

    // Queue the media ids for later processing.
    foreach (array_unique($media_ids) as $media_id) {
      $this->queue->createItem(['media_id' => $media_id]);
      $total++;
    }

    return $total;
  }

  /**
   * Returns the most recent media asset ids.
   *
   * Requests Assets Search API and gets the most recent asset ids available.
   *
   * @return array
   *   List of unique asset ids.
   */
  protected function getAssetIds(): array {
    $asset_ids = [];
    $page = 0;

    do {
      try {
        $page++;
        // Calculate the offset value as a number of previously processed items.
        $offset = $this->getRequestLimit() * ($page - 1);

        // @todo Check if timezone needs to be accounted.
        $date = date('Y-m-d\TH:i:s\Z', $this->state->get('media_acquiadam.last_sync'));

        $response = $this->acquiadam->searchAssets([
          'limit' => $this->getRequestLimit(),
          'offset' => $offset,
          'query' => "lastEditDate:[after $date]",
          'include_deleted' => 'true',
          'include_archived' => 'true',
        ]);
      }
      catch (GuzzleException | InvalidCredentialsException $e) {
        $this->logger->error('Failed to fetch asset ids: @message.',
          ['@message' => $e->getMessage()]);
        return [];
      }

      foreach ($response['assets'] ?? [] as $asset) {
        $asset_ids[] = $asset->id;
      }

    } while (($response['total_count'] ?? 0) > $this->getRequestLimit() * $page);

    return $asset_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestLimit(): int {
    return $this->requestLimit;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestLimit(int $newLimit = 100): int {
    $old_limit = $this->getRequestLimit();
    $this->requestLimit = max(1, $newLimit);
    return $old_limit;
  }

}
