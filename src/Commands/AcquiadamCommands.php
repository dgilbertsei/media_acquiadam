<?php

namespace Drupal\acquiadam\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;

/**
 * Acquia DAM drush commands.
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
   * Sync Acquia DAM medias.
   *
   * @command acquiadam:sync
   * @aliases acquiadam-sync
   * @param string|null method
   * @param string|null date
   */
  public function sync($method = '', $date = '') {
    if (($method && $method === 'delta') || $this->config->get('sync_method') === 'updated_date') {
      $sync_timestamp = \Drupal::state()->get('acquiadam.last_sync');
      // If a specific date has been provided, we need to temporary replace the
      // acquiadam.last_sync state value as it is the one used by sub-processes.
      if ($date) {
        $previous_last_sync = $sync_timestamp;
        $sync_timestamp = strtotime($date);
        \Drupal::state()->set('acquiadam.last_sync', $sync_timestamp);
      }

      acquiadam_refresh_asset_sync_updated_date_queue();

      // If a specific date has been provided, we need to revert the previous
      // state value.
      if ($date) {
        \Drupal::state()->set('acquiadam.last_sync', $previous_last_sync);
      }
    }
    else {
      acquiadam_refresh_asset_sync_queue();
    }
  }

  /**
   * @hook validate sync
   */
  public function validate(CommandData $commandData)
  {
    // Validate the method argument.
    $method = $commandData->input()->getArgument('method');
    if ($method && !in_array($method, ['delta', 'all'])) {
      throw new \Exception(dt("Unknown sync method '!method' Available methods are 'delta' and 'all'.", ['!method' => $method]));
    }

    // @TODO: Validate the date format.
    // @TODO: Validate the date is in the past.
  }
}
