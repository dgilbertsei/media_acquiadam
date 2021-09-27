<?php

namespace Drupal\acquiadam\Controller;

use Drupal\acquiadam\AcquiadamInterface;
use Drupal\acquiadam\Entity\Asset;
use Drupal\acquiadam\Service\AssetImageHelper;
use Drupal\acquiadam\Service\AssetMetadataHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for Acquia DAM routes.
 */
class AcquiadamController extends ControllerBase {

  /**
   * A configured API object.
   *
   * @var \Drupal\acquiadam\Acquiadam
   */
  protected $acquiadam;

  /**
   * The asset that we're going to render details for.
   *
   * @var \Drupal\acquiadam\Entity\Asset
   */
  protected $asset;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Acquia DAM config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Acquia DAM asset image helper service.
   *
   * @var \Drupal\acquiadam\Service\AssetImageHelper
   */
  protected $assetImageHelper;

  /**
   * Acquia DAM asset metadata helper service.
   *
   * @var \Drupal\acquiadam\Service\AssetMetadataHelper
   */
  protected $assetMetadataHelper;

  /**
   * AcquiadamController constructor.
   *
   * @param \Drupal\acquiadam\AcquiadamInterface $acquiadam
   *   The Acquiadam Interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\acquiadam\Service\AssetImageHelper $assetImageHelper
   *   Acquia DAM asset image helper service.
   * @param \Drupal\acquiadam\Service\AssetMetadataHelper $assetMetadataHelper
   *   Acquia DAM asset metadata helper service.
   */
  public function __construct(AcquiadamInterface $acquiadam, ConfigFactoryInterface $configFactory, AssetImageHelper $assetImageHelper, AssetMetadataHelper $assetMetadataHelper) {
    $this->acquiadam = $acquiadam;
    $this->configFactory = $configFactory;
    $this->assetImageHelper = $assetImageHelper;
    $this->assetMetadataHelper = $assetMetadataHelper;

    $this->config = $configFactory->get('acquiadam.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquiadam.acquiadam'),
      $container->get('config.factory'),
      $container->get('acquiadam.asset_image.helper'),
      $container->get('acquiadam.asset_metadata.helper')
    );
  }

  /**
   * Sets the asset details page title.
   *
   * @param int $assetId
   *   The asset ID for the asset to render title for.
   *
   * @return string
   *   The asset details page title.
   */
  public function assetDetailsPageTitle($assetId) {
    $asset = $this->getAsset($assetId);
    return $this->t(
      "Asset details: %filename",
      ['%filename' => $asset->filename]
    );
  }

  /**
   * Get an asset.
   *
   * @param int $assetId
   *   The asset ID for the asset to render details for.
   *
   * @return \Drupal\acquiadam\Entity\Asset|false
   *   The asset or FALSE on failure.
   */
  protected function getAsset($assetId) {
    if (!isset($this->asset)) {
      $this->asset = $this->acquiadam->getAsset($assetId);
    }

    return $this->asset;
  }

  /**
   * Render a page that includes details about an asset.
   *
   * @param int $assetId
   *   The asset ID to retrieve data for.
   *
   * @return array
   *   A render array.
   */
  public function assetDetailsPage($assetId) {

    // Fetch the asset details via the API.
    // @TODO: Check that asset is known by Drupal to avoid exposing assets
    // which are not used in Drupal.
    $asset = $this->getAsset($assetId);

    if (!($asset instanceof Asset)) {
      throw new NotFoundHttpException('Asset does not exist.');
    }

    $asset_attributes = [
      'base_properties' => [],
      'additional_metadata' => [],
    ];
    $asset_preview = NULL;

    $asset_attributes['base_properties']['Asset ID'] = $asset->id;
    $asset_attributes['base_properties']['External ID'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'external_id');
    $asset_attributes['base_properties']['Filename'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'filename');
    $asset_attributes['base_properties']['Description'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'description');
    $asset_attributes['base_properties']['Filetype'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'type');
    $asset_attributes['base_properties']['Date created'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'created_date');
    $asset_attributes['base_properties']['Date modified'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'last_update_date');

    $is_image = 'image' == $asset->file_properties->format_type;
    if ($is_image) {
      $asset_attributes['base_properties']['Width'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'width');
      $asset_attributes['base_properties']['Height'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'height');

      $asset_preview = $this->assetImageHelper->getThumbnailUrlBySize(
        $asset,
        600
      );
    }

    if (isset($asset->security->expiration_date)) {
      $asset_attributes['base_properties']['Expiration Date'] = $this->assetMetadataHelper->getMetadataFromAsset($asset, 'expiration_date');
    }

    return [
      '#theme' => 'asset_details',
      '#asset_data' => $asset_attributes,
      '#asset_preview' => $asset_preview,
      '#asset_link' => "https://{$this->config->get('domain')}/details/asset/{$asset->external_id}",
      '#attached' => [
        'library' => [
          'acquiadam/asset_details',
        ],
      ],
    ];
  }

}
