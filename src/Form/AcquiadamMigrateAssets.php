<?php

namespace Drupal\acquiadam\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AcquiadamMigrateAssets.
 *
 * Migrate Assets.
 */
class AcquiadamMigrateAssets extends FormBase {

  use StringTranslationTrait;

  /**
   * Drupal entity type management service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * AcquiadamMigrateAssets constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquiadam_sync_assets';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['sync_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Import CSV'),
      '#upload_location' => 'public://importcsv/',
      '#default_value' => '',
      "#upload_validators"  => ["file_validate_extensions" => ["csv"]],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync Media'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get all media entity IDs having acquiadam_asset source.
    $existing_entity_ids = acquiadam_get_active_media_ids();
    $sync_file_id = $form_state->getValue(['sync_file', 0]);
    // Run migrate opeation if user uploads any csv and media entity is existing.
    if ($sync_file_id && $existing_entity_ids) {
      $file = $this->entityTypeManager->getStorage('file')->load($sync_file_id);
      $file->setPermanent();
      $file->save();
      // Get data from csv file.
      $data = $this->getCsvData($file->getFileUri(), ',');
      if ($data) {
        $batch = [
          'title' => $this->t('Synchronizing Assets...'),
          'operations' => [
            [
              '\Drupal\acquiadam\Batch\AcquiadamMigrateAssets::syncMedia',
              [
                $existing_entity_ids,
                $data,
              ],
            ],
          ],
          'finished' => '\Drupal\acquiadam\Batch\AcquiadamMigrateAssets::finishBatchOperation',
        ];
        batch_set($batch);
      }
      else {
        $this->messenger->addError('CSV file does not have any data');
      }
    }
  }

  /**
   * Get Data from CSV file.
   *
   * @param string $filename
   *   The CSV File.
   * @param string $delimiter
   *   The delimiter used to fetch data from csv.
   *
   * @return array
   *   An array of data contains in csv file.
   */
  public function getCsvData(string $filename, string $delimiter) {
    $data = [];
    if (($handle = fopen($filename, 'r')) !== FALSE) {
      $counter = 0;
      while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        $counter++;
        if ($counter != 1) {
          $data[trim($row[0])] = trim($row[1]);
        }
      }
      fclose($handle);
    }

    return $data;
  }

}
