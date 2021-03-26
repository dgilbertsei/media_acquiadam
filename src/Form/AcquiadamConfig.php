<?php

namespace Drupal\media_acquiadam\Form;

use cweagans\webdam\Exception\InvalidCredentialsException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\State;
use Drupal\media_acquiadam\ClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AcquiadamConfig.
 *
 * Config form form Acquia dam.
 */
class AcquiadamConfig extends ConfigFormBase {

  const BATCH_SIZE = 5;

  const NUM_IMAGES_PER_PAGE = 12;

  /**
   * Acquia DAM client factory.
   *
   * @var \Drupal\media_acquiadam\ClientFactory
   */
  protected $acquiaDamClientFactory;

  /**
   * The Batch Builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  protected $batchBuilder;

  /**
   * The Drupal DateTime Service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * The Queue Worker Manager Service.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * The Drupal State Service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * AcquiadamConfig constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientFactory $acquiaDamClientFactory, BatchBuilder $batch_builder, TimeInterface $time, QueueWorkerManagerInterface $queue_worker_manager, State $state) {
    parent::__construct($config_factory);
    $this->acquiaDamClientFactory = $acquiaDamClientFactory;
    $this->batchBuilder = $batch_builder;
    $this->time = $time;
    $this->queueWorkerManager = $queue_worker_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('media_acquiadam.settings');

    $form['authentication'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Authentication details'),
    ];

    $form['authentication']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $config->get('username'),
      '#description' => $this->t('The username of the Acquia DAM account to use for API access.'),
      '#required' => TRUE,
    ];

    $form['authentication']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('password'),
      '#description' => $this->t('The password of the Acquia DAM account to use for API access. Note that this field will appear blank even if you have previously saved a value.'),
    ];

    $form['authentication']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('API Client ID to use for API access. Contact the Acquia DAM support team to get one assigned.'),
      '#required' => TRUE,
    ];

    $form['authentication']['secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client secret'),
      '#default_value' => $config->get('secret'),
      '#description' => $this->t('API Client Secret to use for API access. Contact the Acquia DAM support team to get one assigned. Note that this field will appear blank even if you have previously saved a value.'),
    ];

    $form['cron'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cron Settings'),
    ];

    $form['cron']['sync_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Asset refresh interval'),
      '#options' => [
        '-1' => 'Every cron run',
        '3600' => 'Every hour',
        '7200' => 'Every 2 hours',
        '10800' => 'Every 3 hours',
        '14400' => 'Every 4 hours',
        '21600' => 'Every 6 hours',
        '28800' => 'Every 8 hours',
        '43200' => 'Every 12 hours',
        '86400' => 'Every 24 hours',
      ],
      '#default_value' => empty($config->get('sync_interval')) ? 3600 : $config->get('sync_interval'),
      '#description' => $this->t('How often should Acquia DAM assets saved in this site be synced with Acquia DAM (this includes asset metadata as well as the asset itself)?'),
      '#required' => TRUE,
    ];
    $form['cron']['notifications_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable notification-based synchronization'),
      '#description' => $this->t('Faster synchronization method based on Notifications from the API.'),
      '#default_value' => $config->get('notifications_sync'),
    ];

    $form['image'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Image configuration'),
    ];

    $form['image']['size_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Image size limit'),
      '#description' => $this->t('Limit the source size used when importing image assets. The largest available size up to the selected will be used.'),
      '#options' => [
        -1 => $this->t('Original size'),
        100 => 100,
        150 => 150,
        220 => 220,
        310 => 310,
        550 => 550,
        1280 => 1280,
      ],
      '#default_value' => empty($config->get('size_limit')) ? -1 : $config->get('size_limit'),
    ];

    $form['manual_sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Manual asset synchronization.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['manual_sync']['perform_manual_sync'] = [
      '#type' => 'submit',
      '#value' => $this->t('Synchronize all media assets'),
      '#name' => 'perform_manual_sync',
      '#submit' => [[$this, 'performManualSync']],
    ];
    $form['entity_browser'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Acquia DAM entity browser settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['entity_browser']['num_images_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of images per page'),
      '#default_value' => $config->get('num_images_per_page') ?? self::NUM_IMAGES_PER_PAGE,
      '#description' => $this->t('Number of images to be shown per page in the entity browser can be set using this field. Default is set to 12 images.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler for "Synchronize all media assets" button.
   *
   * @param array $form
   *   A Drupal form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   *
   * @return array|bool
   *   An array of Media IDs that were set for a batch job, or FALSE on no
   *   media items found.
   */
  public function performManualSync(array &$form, FormStateInterface $form_state) {
    $media_ids = $this->getActiveMediaIds();
    if (!$media_ids) {
      // No assets to synchronize.
      $this->messenger()
        ->addWarning($this->t('The synchronization is canceled because no Assets were found.'));
      return FALSE;
    }

    $this->batchBuilder
      ->setTitle($this->t('Synchronizing media assets.'))
      ->setInitMessage($this->t('Starting synchronization...'))
      ->setProgressMessage($this->t('@elapsed elapsed. Approximately @estimate left.'))
      ->setErrorMessage($this->t('An error has occurred.'))
      ->setFinishCallback([$this, 'finishBatchOperation'])
      ->addOperation([$this, 'processBatchItems'], [$media_ids]);

    $this->batchSet($this->batchBuilder->toArray());

    return $media_ids;
  }

  /**
   * Wrapper for media_acquiadam_get_active_media_ids().
   *
   * This method exists so the functionality can be overridden in unit tests.
   */
  protected function getActiveMediaIds() {
    return media_acquiadam_get_active_media_ids();
  }

  /**
   * Wrapper for batch_set().
   *
   * This method exists so the functionality can be overridden in unit tests.
   *
   * @param array $batch_builder
   *   The array representation of the BatchBuilder object.
   */
  protected function batchSet(array $batch_builder) {
    batch_set($batch_builder);
  }

  /**
   * Processes batch items.
   *
   * @param array $media_ids
   *   Items to process.
   * @param array $context
   *   Context.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function processBatchItems(array $media_ids, array &$context) {
    /** @var \Drupal\media_acquiadam\Plugin\QueueWorker\AssetRefresh $asset_refresh_queue_worker */
    $asset_refresh_queue_worker = $this->queueWorkerManager
      ->createInstance('media_acquiadam_asset_refresh');

    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = $context['results']['processed'] = 0;
      $context['sandbox']['max'] = count($media_ids);
      $context['sandbox']['items'] = $media_ids;
      $context['results']['total'] = $context['sandbox']['max'];
      $context['results']['start_time'] = $this->time->getRequestTime();
    }

    $media_ids = array_splice($context['sandbox']['items'], 0, self::BATCH_SIZE);
    foreach ($media_ids as $media_id) {
      try {
        if ($asset_refresh_queue_worker->processItem(['media_id' => $media_id])) {
          $context['results']['processed']++;
        }
      }
      catch (\Exception $e) {
        $this->logger('media_acquiadam')->error(
          'Failed to update media entity id = :id. Message: :message',
          [
            ':id' => $media_id,
            ':message' => $e->getMessage(),
          ]);
      }

      $context['sandbox']['progress']++;
      $context['message'] = $this->t('Processed :progress media assets out of :count.', [
        ':progress' => $context['sandbox']['progress'],
        ':count' => $context['sandbox']['max'],
      ]);
    }

    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Finish callback for the batch operation.
   *
   * @param bool $success
   *   The Success flag.
   * @param array $results
   *   Results.
   * @param array $operations
   *   Operations.
   */
  public function finishBatchOperation($success, array $results, array $operations) {
    $message = $this->getStringTranslation()->formatPlural(
      $results['processed'],
      '1 asset (out of @total) has been synchronized.',
      '@count assets (out of @total) have been synchronized.',
       ['@total' => $results['total']]);
    $this->messenger()->addStatus($message);

    if ($results['processed'] === $results['total']) {
      // Reset all Drupal States related to the automatic asset synchronization.
      $this->state->set('media_acquiadam.notifications_starttime', $results['start_time']);
      $this->state->set('media_acquiadam.notifications_endtime', NULL);
      $this->state->set('media_acquiadam.notifications_next_page', NULL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('media_acquiadam.client_factory'),
       new BatchBuilder(),
      $container->get('datetime.time'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquiadam_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'media_acquiadam.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // We set the client data array with the values from form_state.
    $username = $form_state->getValue('username');
    $password = $this->getFieldValue($form_state, 'password');
    $form_state->setValue('password', $password);
    $client_id = $form_state->getValue('client_id');
    $client_secret = $this->getFieldValue($form_state, 'secret');
    $form_state->setValue('secret', $client_secret);

    try {
      $acquiadam_client = $this->acquiaDamClientFactory->getWithCredentials($username, $password, $client_id, $client_secret);
      $acquiadam_client->getAccountSubscriptionDetails();
    }
    // If checkCredentials() throws an exception,
    // we catch it here and display the error message to the user.
    catch (InvalidCredentialsException $e) {
      $form_state->setErrorByName('authentication', $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('media_acquiadam.settings')
      ->set('username', $form_state->getValue('username'))
      ->set('password', $form_state->getValue('password'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('secret', $form_state->getValue('secret'))
      ->set('sync_interval', $form_state->getValue('sync_interval'))
      ->set('size_limit', $form_state->getValue('size_limit'))
      ->set('notifications_sync', $form_state->getValue('notifications_sync'))
      ->set('num_images_per_page', $form_state->getValue('num_images_per_page'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Gets a form value from stored config.
   *
   * @param string $field_name
   *   The key of the field in the simple config.
   *
   * @return mixed
   *   The value for the given form field, or NULL.
   */
  protected function getFormValueFromConfig($field_name) {
    $config_name = $this->getEditableConfigNames();
    $value = $this->config(reset($config_name))->get($field_name);
    return $value;
  }

  /**
   * Gets a form field value, either from the form or from config.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $field_name
   *   The key of the field in config. (This may differ from form field key).
   *
   * @return mixed
   *   The value for the given form field, or NULL.
   */
  protected function getFieldValue(FormStateInterface $form_state, $field_name) {
    // If the user has entered a value use it, if not check config.
    $value = $form_state->getValue($field_name) ?: $this->getFormValueFromConfig($field_name);
    return $value;
  }

}
