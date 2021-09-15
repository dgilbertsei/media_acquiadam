<?php

namespace Drupal\acquiadam\Service;

use Drupal\acquiadam\Exception\InvalidCredentialsException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\acquiadam\AcquiadamInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AssetRefreshManager.
 *
 * Adds media items to the asset sync queue for later processing.
 * Uses the Notifications API to get affected asset ids - determines which
 * assets where changed within the given period of time, and adds them to the
 * queue.
 *
 * @package Drupal\acquiadam
 */
class AssetRefreshManager implements AssetRefreshManagerInterface, ContainerInjectionInterface {

  /**
   * The Acquiadam Service.
   *
   * @var \Drupal\acquiadam\AcquiadamInterface
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
   * The Drupal DateTime Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The maximum number of items to return in Notifications API response.
   *
   * @var int
   */
  protected $requestLimit = 250;

  /**
   * The default last read interval is 12 hours.
   *
   * @var int
   */
  protected $lastReadInterval = 43200;

  /**
   * Returns the list of notification actions to track.
   *
   * @return string[]
   *   List of action (machine) names.
   */
  protected function getActionsToTrack(): array {
    return [
      'asset_version',
      'asset_property',
      'asset_delete',
    ];
  }

  /**
   * Returns the list of item types to track.
   *
   * @return string[]
   *   List of item types (machine) names.
   */
  protected function getItemsTypesToTrack(): array {
    return [
      'asset',
      'video',
      'image',
      'document',
      'audio',
    ];
  }

  /**
   * AssetRefreshManager constructor.
   *
   * @param \Drupal\acquiadam\AcquiadamInterface $acquiadam
   *   The Acquiadam Service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal State Service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The Logger Factory Service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The Queue Factory Service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Drupal DateTime Service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AcquiadamInterface $acquiadam, StateInterface $state, LoggerChannelFactoryInterface $logger_factory, QueueFactory $queue_factory, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->acquiadam = $acquiadam;
    $this->state = $state;
    $this->logger = $logger_factory->get('acquiadam');
    $this->queue = $queue_factory->get($this->getQueueName());
    $this->mediaStorage = $entity_type_manager->getStorage('media');
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquiadam.acquiadam'),
      $container->get('state'),
      $container->get('logger.factory'),
      $container->get('queue'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQueueName(): string {
    return 'acquiadam_asset_refresh';
  }

  /**
   * {@inheritdoc}
   */
  public function updateQueue(array $asset_id_fields) {
    if (empty($asset_id_fields)) {
      // Nothing to process. Associated media bundles are not found.
      $this->saveStartTime($this->getEndTime());
      return 0;
    }

    // Get ids of the changed (updated/deleted) assets.
    $asset_ids = $this->getAssetIds();
    if (!$asset_ids) {
      // Nothing to process.
      return 0;
    }

    // Add media entity ids to the queue.
    $total = 0;
    $media_query = $this->mediaStorage->getQuery();
    foreach ($asset_id_fields as $bundle => $field) {
      $media_query->condition(
        $media_query->orConditionGroup()
          ->condition('bundle', $bundle)
          ->condition($field, $asset_ids, 'IN')
      );
    }
    $media_ids = $media_query->execute();

    foreach ($media_ids as $media_id) {
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
    try {
      $page = $this->getNextPage() ? $this->getNextPage() : 1;
      // Calculate the offset value as a number of previously processed items.
      $offset = $this->getRequestLimit() * ($page - 1);

      // @TODO: Deal with the timezone.
      $date = date('Y-m-dTH:i:sZ', \Drupal::state()->get('acquiadam.last_sync'));

      $response = $this->acquiadam->searchAssets([
        'limit' => $this->getRequestLimit(),
        'offset' => $offset,
        'query' => ["lastEditDate:[after $date]"],
      ]);
    }
    catch (GuzzleException | InvalidCredentialsException $e) {
      $this->logger->error('Failed to fetch asset ids: @message.',
        ['@message' => $e->getMessage()]);
      return [];
    }

    if (empty($response['items'])) {
      $continue_fetch = FALSE;
      $asset_ids = [];
    }
    else {
      $continue_fetch = $response['total_count'] > $this->getRequestLimit() * $page;
      $asset_ids = array_unique(array_map(function($item) { return $item['id']; }, $response['items']));
    }

    if ($continue_fetch) {
      $this->saveNextPage(++$page);
      return $asset_ids;
    }

    $this->resetNextPage();

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

  /**
   * Returns the "Next Page" Drupal State value.
   *
   * @return int|null
   *   Page index.
   */
  protected function getNextPage(): ?int {
    return $this->state->get('acquiadam.notifications_next_page');
  }

  /**
   * Saves the "Next Page" Drupal State value.
   *
   * @param int $page
   *   Page index.
   */
  protected function saveNextPage(int $page) {
    $this->state->set('acquiadam.notifications_next_page', $page);
  }

  /**
   * Resets the "Next Page" value to the Drupal State.
   */
  protected function resetNextPage() {
    $this->state->set('acquiadam.notifications_next_page', NULL);
  }

}
