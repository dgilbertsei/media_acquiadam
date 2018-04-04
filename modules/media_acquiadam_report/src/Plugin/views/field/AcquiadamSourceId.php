<?php

namespace Drupal\media_acquiadam_report\Plugin\views\field;

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
   * Subscription details for the account.
   *
   * @return \stdClass
   *   Returns a stdClass with the account properties.
   */
  protected $subscriptionDetails;
 
  /**
   * Constructs a AcquiadamSourceId Views field object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AcquiadamInterface $acquiadam) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->acquiadam = $acquiadam;
    $this->subscriptionDetails = $this->acquiadam->getAccountSubscriptionDetails();
    $this->asset_id_fields = media_acquiadam_get_bundle_asset_id_fields();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('media_acquiadam.acquiadam')
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
      'display_as_link' => 'Display ID as link to DAM source',
      'display_as_text' => 'Display ID as text'
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
  public function render(ResultRow $values) {
    $media = $values->_entity;
    $bundle_id = $media->bundle();
    $dam_url = $this->subscriptionDetails->url;

    // Render asset link only for media assets in an acquiadam bundles.
    if (array_key_exists($bundle_id, $this->asset_id_fields)) {
      $asset_id = $media->{$this->asset_id_fields[$bundle_id]}->value;
      // Display as a link or text.
      if ($this->options['display_option'] == 'display_as_link') {
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
        return $asset_id;
      }
    }
  }
}
