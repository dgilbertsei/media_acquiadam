<?php

namespace Drupal\media_acquiadam\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media_acquiadam\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Report Acquia DAM asset usage via integration links.
 *
 * @QueueWorker (
 *   id = "media_acquiadam_integration_link_report",
 *   title = @Translation("Acquia DAM Integration Link Report"),
 *   cron = {"time" = 30}
 * )
 */
class IntegrationLinkReport extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Acquia DAM client factory.
   *
   * @var \Drupal\media_acquiadam\Client
   */
  protected $client;

  /**
   * Drupal logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, LoggerChannelInterface $loggerChannel) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->loggerChannel = $loggerChannel;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('media_acquiadam.client'),
      $container->get('logger.factory')->get('media_acquiadam')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return bool
   *   TRUE if the integration link properly reported, FALSE - otherwise.
   */
  public function processItem($data) {
    // If we don't have all the data to report the usage, don't go further.
    if (empty($data['mid']) || empty($data['asset_uuid']) || empty($data['url'])) {
      return FALSE;
    }

    try {
      $post = [
        'assetUuid' => $data['asset_uuid'],
        'description' => "Acquia DAM module: Asset referenced from Media Entity {$data['mid']}",
        'url' => $data['url'],
      ];

      $this->client->registerIntegrationLink($post);
    }
    catch (\Exception $x) {
      $this->loggerChannel->error(
        'Error trying to create the integration link for @media_id',
        ['@media_id' => $data['mid']]
      );
      return FALSE;
    }

    return TRUE;
  }

}
