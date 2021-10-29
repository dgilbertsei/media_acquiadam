<?php

namespace Drupal\media_acquiadam\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;

/**
 * Acquia DAM drush commands.
 *
 * @package Drupal\media_acquiadam\Commands
 */
class AcquiadamCommands extends DrushCommands {

  /**
   * The acquiadam configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueService;

  /**
   * The state interface.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, QueueFactory $queue_service, StateInterface $state, TimeInterface $time) {
    parent::__construct();
    $this->config = $config_factory->get('media_acquiadam.settings');
    $this->queueService = $queue_service;
    $this->state = $state;
    $this->time = $time;
  }

  /**
   * Update Media: Process a CSV to update Acquia DAM assets to reference.
   *
   * This will update Acquia DAM media to change the asset_id from the
   * legacy Acquia DAM to the new Acquia DAM.
   *
   * @param string $file
   *   The path to the migrate file.
   * @param array $options
   *   The options of the command.
   *
   * @command acquiadam:update
   * @aliases acquiadam-update
   *
   * @option string $delimiter
   *   The CSV delimited.
   */
  public function update(string $file, array $options = ['delimiter' => ',']) {
    $legacy_ids_to_new_ids = _media_acquiadam_parse_reference_updation_csv($file, $options['delimiter']);

    $batch = _media_acquiadam_build_reference_updation_batch($legacy_ids_to_new_ids);

    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * Validate handler for the acquiadam:update command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook validate acquiadam:update
   *
   * @throws \Exception
   */
  public function validateUpdate(CommandData $commandData) {
    $file = $commandData->input()->getArgument('file');

    if (!file_exists($file)) {
      throw new \Exception(dt('Impossible to load the file.'));
    }

  }

  /**
   * Sync Acquia DAM medias.
   *
   * @command acquiadam:sync
   * @aliases acquiadam-sync
   * @option string method The synchronization methods. Valid values are "delta" (to sync assets which have been updated since a date) or "all" to synchronize all the medias. If not method is provided, the method configured on the cron settings will be used.
   * @option string date In case the "delta" method is chosen, the assets which have been updated since that date will be synchronized. If no date is provided, the last synchronization date will be used. Valid format is YYYY-MM-DDTHH:MM:SS (2021-09-30T01:00:00 for example).
   * @usage drush acquiadam:sync
   * @usage drush acquiadam:sync --method=delta
   * @usage drush acquiadam:sync --method=delta --date=2021-01-01T00:00:00
   * @usage drush acquiadam:sync --method=all
   */
  public function sync($options = ['method' => NULL, 'date' => NULL]) {
    if (($options['method'] && $options['method'] === 'delta') || $this->config->get('sync_method') === 'updated_date') {
      $sync_timestamp = $this->state->get('media_acquiadam.last_sync');
      // If specific date has been provided, we replace the acquiadam.last_sync
      // state value as it is the one used by sub-processes.
      if ($options['date']) {
        $sync_timestamp = strtotime($options['date']);
        $this->state->set('media_acquiadam.last_sync', $sync_timestamp);
      }

      $this->logger()->notice(dt('Fetching and queuing for synchronization the assets which have been updated since @date.', ['@date' => date('c', $sync_timestamp)]));

      media_acquiadam_refresh_asset_sync_updated_date_queue();
    }
    else {
      $this->logger()->notice(dt("Queuing all the Acquia DAM's related media for synchronization."));

      media_acquiadam_refresh_asset_sync_queue();
    }

    $this->state->set('media_acquiadam.last_sync', $this->time->getRequestTime());

    $total_queue_items = $this->queueService->get('media_acquiadam_asset_refresh')->numberOfItems();
    $this->logger()->success(dt('@total_queue_items medias are queued for sync.', ['@total_queue_items' => $total_queue_items]));
  }

  /**
   * The validation handler for the acquiadam:sync command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook validate acquiadam:sync
   *
   * @throws \Exception
   */
  public function validateSync(CommandData $commandData) {
    // Validate the method argument.
    $method = $commandData->input()->getOption('method');
    if ($method && !in_array($method, ['delta', 'all'])) {
      throw new \Exception(dt("Unknown sync method '!method' Available methods are 'delta' and 'all'.", ['!method' => $method]));
    }

    $date = $commandData->input()->getOption('date');
    if ($date) {
      if (strtotime($date) === FALSE) {
        throw new \Exception(dt("The given date is not valid. Expected format: 2021-09-30T01:00:00."));
      }
      if (strtotime($date) >= time()) {
        throw new \Exception(dt("The date must be in the past."));
      }
    }
  }

}
