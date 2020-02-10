<?php

namespace Drupal\media_acquiadam\Service;

use cweagans\webdam\Exception\InvalidCredentialsException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\media_acquiadam\AcquiadamInterface;
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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Drupal DateTime Service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AcquiadamInterface $acquiadam, StateInterface $state, LoggerChannelFactoryInterface $logger_factory, QueueFactory $queue_factory, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->acquiadam = $acquiadam;
    $this->state = $state;
    $this->logger = $logger_factory->get('media_acquiadam');
    $this->queue = $queue_factory->get($this->getQueueName());
    $this->mediaStorage = $entity_type_manager->getStorage('media');
    $this->time = $time;
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
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
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
   * Requests Notifications API and gets the most recent asset ids available.
   *
   * @return array
   *   List of unique asset ids.
   */
  protected function getAssetIds(): array {
    try {
      $page = $this->getNextPage() ? $this->getNextPage() : 1;
      // Calculate the offset value as a number of previously processed items.
      $offset = $this->getRequestLimit() * ($page - 1);
      $response = $this->acquiadam->getNotifications([
        'limit' => $this->getRequestLimit(),
        'offset' => $offset,
        'starttime' => $this->getStartTime(),
        'endtime' => $this->getEndTime(),
      ]);
    }
    catch (GuzzleException | InvalidCredentialsException $e) {
      $this->logger->error('Failed to fetch asset ids: @message.',
        ['@message' => $e->getMessage()]);
      return [];
    }

    if (empty($response['notifications'])) {
      $continue_fetch = FALSE;
      $asset_ids = [];
    }
    else {
      $continue_fetch = $response['total'] > $this->getRequestLimit() * $page;
      $asset_ids = $this->extractAssetIds($response['notifications']);
    }

    if ($continue_fetch) {
      $this->saveNextPage(++$page);
      $this->saveEndTime($this->getEndTime());
      return $asset_ids;
    }

    $this->saveStartTime($this->getEndTime());
    $this->resetEndTime();
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
  public function setRequestLimit(int $newLimit = 250): int {
    $old_limit = $this->getRequestLimit();
    $this->requestLimit = max(1, $newLimit);
    return $old_limit;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastReadInterval(): int {
    return $this->lastReadInterval;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastReadInterval(int $lastReadInterval = 43200): int {
    $old_interval = $this->getLastReadInterval();
    $this->lastReadInterval = max(1, $lastReadInterval);
    return $old_interval;
  }

  /**
   * Extracts asset id values from the response body.
   *
   * @param array $notifications
   *   Notification items.
   *
   * @return array
   *   List of asset ids.
   */
  protected function extractAssetIds(array $notifications): array {
    $asset_ids = [];
    foreach ($notifications as $item) {
      if (!in_array($item['action'], $this->getActionsToTrack(), TRUE)) {
        continue;
      }

      if (isset($item['source']['type']) && in_array($item['source']['type'], $this->getItemsTypesToTrack(), TRUE)) {
        $asset_ids[] = $item['source']['id'];
      }

      if (!isset($item['subitems']) || !is_array($item['subitems'])) {
        continue;
      }

      foreach ($item['subitems'] as $subitem) {
        if (!isset($subitem['type'], $subitem['id'])) {
          continue;
        }
        if (!in_array($subitem['type'], $this->getItemsTypesToTrack(), TRUE)) {
          continue;
        }
        if (in_array($subitem['id'], $asset_ids)) {
          continue;
        }

        $asset_ids[] = $subitem['id'];
      }
    }

    return array_unique($asset_ids);
  }

  /**
   * Returns the "Start Time" Drupal State value.
   *
   * As a query parameter, filters out all older (by date created) items in
   * Notifications API.
   *
   * @return int
   *   Timestamp.
   */
  protected function getStartTime(): int {
    $start_time_timestamp = $this->state->get('media_acquiadam.notifications_starttime');
    if ($start_time_timestamp) {
      return $start_time_timestamp;
    }

    // Setting up the default value.
    $default_start_time_timestamp = $this->time->getRequestTime() - $this->getLastReadInterval();
    $this->saveStartTime($default_start_time_timestamp);

    return $default_start_time_timestamp;
  }

  /**
   * Saves the "Start Time" Drupal State value.
   *
   * @param int $timestamp
   *   Timestamp.
   */
  protected function saveStartTime(int $timestamp) {
    $this->state->set('media_acquiadam.notifications_starttime', $timestamp);
  }

  /**
   * Returns the "End Time" Drupal State value.
   *
   * As a query parameter, filters out all newer (by date created, inclusively)
   * items in Notifications API.
   *
   * @return int
   *   Timestamp.
   */
  protected function getEndTime(): ?int {
    return $this->state->get('media_acquiadam.notifications_endtime', $this->time->getRequestTime());
  }

  /**
   * Saves the "End Time" Drupal State value.
   *
   * @param int $timestamp
   *   Timestamp.
   *
   * @see \Drupal\media_acquiadam\Service\AssetRefreshManager::resetEndTime()
   */
  protected function saveEndTime(int $timestamp) {
    if ($this->state->get('media_acquiadam.notifications_endtime')) {
      // Do not override the state if previously was set to non-zero value.
      return;
    }

    $this->state->set('media_acquiadam.notifications_endtime', $timestamp);
  }

  /**
   * Resets the "End Time" Drupal State value.
   */
  protected function resetEndTime() {
    $this->state->set('media_acquiadam.notifications_endtime', NULL);
  }

  /**
   * Returns the "Next Page" Drupal State value.
   *
   * @return int|null
   *   Page index.
   */
  protected function getNextPage(): ?int {
    return $this->state->get('media_acquiadam.notifications_next_page');
  }

  /**
   * Saves the "Next Page" Drupal State value.
   *
   * @param int $page
   *   Page index.
   */
  protected function saveNextPage(int $page) {
    $this->state->set('media_acquiadam.notifications_next_page', $page);
  }

  /**
   * Resets the "Next Page" value to the Drupal State.
   */
  protected function resetNextPage() {
    $this->state->set('media_acquiadam.notifications_next_page', NULL);
  }

}
