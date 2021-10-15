<?php

namespace Drupal\acquiadam\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\DrushCommands;
use Drupal\acquiadam\Form\AcquiadamMigrateAssets;

/**
 * Acquia DAM drush commands.
 *
 * @package Drupal\acquiadam\Commands
 */
class AcquiadamCommands extends DrushCommands {

  /**
   * The acquiadam configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct();
    $this->config = $config_factory->get('acquiadam.settings');
  }

  /**
   * Migrate Acquia DAM assets from media_acquiadam to acquiadam.
   *
   * @command acquiadam:migrate
   * @aliases acquiadam-migrate
   *
   * @param string $file The path to the migrate file.
   * @option string $delimiter The CSV delimited.
   */
  public function migrate($file, $options = ['delimiter' => ',']) {
    $legacy_ids_to_new_ids = _acquiadam_parse_migration_csv($file, $options['delimiter']);

    $batch = _acquiadam_build_migration_batch($legacy_ids_to_new_ids);

    batch_set($batch);
    drush_backend_batch_process();
  }

  /**
   * @hook validate acquiadam:migrate
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @throws \Exception
   * @return void
   */
  public function validateMigrate(CommandData $commandData) {
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
  public function sync($options = ['method' => null, 'date' => null]) {
    if (($options['method'] && $options['method'] === 'delta') || $this->config->get('sync_method') === 'updated_date') {
      $sync_timestamp = \Drupal::state()->get('acquiadam.last_sync');
      // If a specific date has been provided, we need to temporary replace the
      // acquiadam.last_sync state value as it is the one used by sub-processes.
      if ($options['date']) {
        $previous_last_sync = $sync_timestamp;
        $sync_timestamp = strtotime($options['date']);
        \Drupal::state()->set('acquiadam.last_sync', $sync_timestamp);
      }

      $this->logger()->notice(dt('Fetching and queuing for synchronization the assets which have been updated since @date.', ['@date' => date('c', $sync_timestamp)]));

      acquiadam_refresh_asset_sync_updated_date_queue();

      // If a specific date has been provided, we need to revert the previous
      // state value.
      if ($options['date']) {
        \Drupal::state()->set('acquiadam.last_sync', $previous_last_sync);
      }
    }
    else {
      $this->logger()->notice(dt("Queuing all the Acquia DAM's related media for synchronization."));

      acquiadam_refresh_asset_sync_queue();
    }

    $total_queue_items = \Drupal::queue('acquiadam_asset_refresh')->numberOfItems();
    $this->logger()->success(dt('@total_queue_items medias have been queued for sync.', ['@total_queue_items' => $total_queue_items]));
  }

  /**
   * @hook validate acquiadam:sync
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @throws \Exception
   * @return void
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
