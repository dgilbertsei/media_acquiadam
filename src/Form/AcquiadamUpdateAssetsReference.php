<?php

namespace Drupal\media_acquiadam\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AcquiadamUpdateAssetsReference.
 *
 * Update Assets Reference.
 */
class AcquiadamUpdateAssetsReference extends FormBase {

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
   * AcquiadamUpdateAssetsReference constructor.
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
    return 'media_acquiadam_sync_assets';
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
    $file_id = $form_state->getValue(['sync_file', 0]);

    // If we can't get the file which have been uploaded, display an error and
    // stop the process.
    if (!$file_id) {
      $this->messenger->addError('CSV file does not have any data');
      return;
    }

    // Mark the file as permanent so it does not get deleted.
    $file = $this->entityTypeManager->getStorage('file')->load($file_id);
    $file->setPermanent();
    $file->save();

    // Parse the csv to get an associative array. Key is the legacy asset_id,
    // value is the new asset_id.
    $legacy_ids_to_new_ids = _media_acquiadam_parse_reference_updation_csv($file->getFileUri(), ',');

    $batch = _media_acquiadam_build_reference_updation_batch($legacy_ids_to_new_ids);

    batch_set($batch);
  }

}
