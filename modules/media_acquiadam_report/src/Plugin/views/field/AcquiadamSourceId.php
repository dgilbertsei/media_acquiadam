<?php

namespace Drupal\media_acquiadam_report\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the acquiadam_asset source id.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("acquiadam_source_id")
 */
class AcquiadamSourceId extends FieldPluginBase {

  /**
   * A configured API object.
   *
   * @var \Drupal\media_acquiadam\AcquiadamInterface
   */
  protected $acquiadam;

  /**
   * Array of Acquia DAM asset id fields keyed by bundle.
   *
   * @var array
   */
  protected $asset_id_fields;
 
  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Subscription details for the account.
   *
   * @return \stdClass
   *   Returns a stdClass with the account properties.
   */
  protected $subscriptionDetails;
 
  /**
   * Constructs a AcquiadamSourceId Views field object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AcquiadamInterface $acquiadam, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->acquiadam = $acquiadam;
    $this->connection = $connection;
    $this->subscriptionDetails = $this->acquiadam->getAccountSubscriptionDetails();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('media_acquiadam.acquiadam'),
      $container->get('database')
    );
  }

  /**
   * Define the available options
   * 
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['display_option'] = ['default' => 'display_as_text'];
 
    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) { 
    $options = [
      'display_as_link' => $this->t('Display ID as link to DAM source'),
      'display_as_text' => $this->t('Display ID as text')
    ];

    $form['display_option'] = array(
      '#title' => $this->t('Asset ID display options'),
      '#type' => 'select',
      '#default_value' => $this->options['display_option'],
      '#options' => $options,
    );
 
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Skip query.
  }

  /**
   * @{inheritdoc}
   */
  public function preRender(&$values) {
    // The bundle ID and asset ID field.
    $this->asset_id_fields = media_acquiadam_get_bundle_asset_id_fields();
    // Asset IDs for a single query against the acquiadam_assets_data table.
    $asset_ids = [];

    // Add asset id and source data to each row for field rendering.
    foreach ($values as $row_id => $row) {
      $media = $row->_entity;
      $bundle_id = $media->bundle();

      // If the media bundle is a DAM asset, add additional data. 
      if (array_key_exists($bundle_id, $this->asset_id_fields)) {
        $asset_id = $media->{$this->asset_id_fields[$bundle_id]}->value;
        // Set the key as the asset_id so we can intersect with below query result.
        $asset_ids[$asset_id] = $row_id;
        
        // Add data to reference.
        $row->acquiadam_asset_data = [
          'asset_id' => $asset_id,
          'asset_has_source' => TRUE
        ];
      }
    }

    // Query for asset IDs missing a source.
    $orphaned_assets = $this->connection->select('acquiadam_assets_data', 'ad')
      ->fields('ad')
      ->condition('name', 'remote_deleted')
      ->condition('asset_id', array_keys($asset_ids), 'IN')
      ->execute()
      ->fetchAllAssoc('asset_id');
    
    // The view rows missing assets.
    $rows_missing_source_assets = array_intersect_key($asset_ids, $orphaned_assets);
    
    // Update the asset_has_source values.
    foreach ($rows_missing_source_assets as $asset_id => $row_id) {
      $values[$row_id]->acquiadam_asset_data['asset_has_source'] = FALSE;
    }
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $dam_url = $this->subscriptionDetails->url;

    // Render asset ID for Acquia DAM assets.
    // For orphaned sources, omit the link and prefix with missing text.
    if ($values->acquiadam_asset_data) {
      $asset_id = $values->acquiadam_asset_data['asset_id'];
      $has_source = $values->acquiadam_asset_data['asset_has_source'];
      $source_missing_text = ($has_source ? '' : $this->t('Missing: '));

      // Display as a link or text.
      if ($has_source && $this->options['display_option'] == 'display_as_link') {
        // Link to source DAM asset.
        $external_asset_url = Url::fromUri(
          'https://' . $dam_url . '/cloud/#asset/' . $asset_id
        );
        return [
          '#type' => 'link',
          '#title' => $asset_id,
          '#url' => $external_asset_url
        ];
      }
      else {
        return $source_missing_text . $asset_id;
      }
    }
  }
}
