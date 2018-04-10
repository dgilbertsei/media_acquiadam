<?php
/**
 * @file
 * Implementation of queue worker for converting assets between types.
 */

namespace Drupal\media_acquiadam_convert\Plugin\QueueWorker;

use Drupal;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\media_acquiadam\AcquiadamInterface;
use Drupal\media_acquiadam\AssetDataInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for checking if an asset needs to be converted to another type.
 *
 * @QueueWorker (
 *   id = "media_acquiadam_asset_convert",
 *   title = @Translation("Acquia DAM Asset Type Convert"),
 *   cron = {"time" = 30}
 * )
 */
class AssetConvert extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /** @var \Psr\Log\LoggerInterface $logger */
  protected $logger;

  /** @var \Drupal\media_acquiadam\Client $damClient */
  protected $damClient;

  /** @var \Drupal\media_acquiadam\AssetData $assetData */
  protected $assetData;

  /** @var \Drupal\Core\Queue\QueueInterface $convertQueue */
  protected $convertQueue;

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger to log to.
   * @param \Drupal\media_acquiadam\Client $dam
   *   An Acquia DAM client.
   * @param \Drupal\media_acquiadam\AssetData $assetData
   *   Acquia DAM assets data service.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   Queue to add items to when they need secondary processing.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, AcquiadamInterface $dam, AssetDataInterface $assetData, QueueInterface $queue) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->damClient = $dam;
    $this->assetData = $assetData;
    $this->convertQueue = $queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      Drupal::logger('media_acquiadam'),
      media_acquiadam_convert_get_background_dam_client(),
      Drupal::service('media_acquiadam.asset_data'),
      Drupal::queue('media_acquiadam_asset_convert'));
  }

  /**
   * Process a queued item.
   *
   * {@inheritdoc}
   *
   * @param array $data
   *   A keyed array with the following values:
   *     assetID: The asset ID.
   *     folderID: The folder ID to limit the check to.
   *     filename: The name of the file.
   *     originalType: The original file type.
   *     destinationType: The desired file type.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function processItem($data) {

    if (!$this->validateItemData($data)) {
      return;
    }

    // Default to the download stage if this is the first run or we haven't
    // gotten a valid downloadKey stored.
    if (empty($data['stage']) || empty($data['downloadKey'])) {
      $data['stage'] = 'queue_download';
    }

    // Since queue items can be processed multiple times in a single cron run
    //  we need to have a way to ensure we aren't blowing through our steps
    // prematurely. This is primarily used (in combination with a stored count)
    // for limiting the check_upload stage.
    $data['last_run'] = \REQUEST_TIME;

    switch ($data['stage']) {
      case 'queue_download':
        $this->processStageQueueDownload($data);
        break;
      case 'check_upload':
        $this->processStageUpload($data);
        break;
      case 'update_metadata':
        $this->processStageUpdateMetadata($data);
        break;
      default:
        $this->logger->error('Got unhandled stage @stage when processing @assetID.', [
          '@stage' => $data['stage'],
          '@assetID' => $data['assetID'],
        ]);
    }
  }

  /**
   * Validate that the queue item data is sane.
   *
   * @param array $data
   *   An array of data for the queue item. Values will vary based on the
   *   current stage.
   *
   * @return bool
   *   TRUE if the item data passes basic sanity checks. FALSE otherwise.
   */
  protected function validateItemData($data) {
    if (empty($data['assetID'])) {
      $this->logger->error('Asset ID was missing for type conversion process.');
      return FALSE;
    }
    elseif (empty($data['filename'])) {
      $this->logger->error('Asset filename was missing for type conversion of @assetID.', ['@assetID' => $data['assetID']]);
      return FALSE;
    }
    elseif (empty($data['originalType'])) {
      $this->logger->error('Original type was missing for type conversion of @assetID (@filename).', [
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
      ]);
      return FALSE;
    }
    elseif (empty($data['destinationType'])) {
      $this->logger->error('Destination type was missing for type conversion of @assetID (@filename).', [
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
      ]);
      return FALSE;
    }
    elseif (!empty($data['last_run']) && $data['last_run'] == \REQUEST_TIME) {
      throw new RequeueException('Conversion queue rate limiting was triggered.');
    }

    $convert_key = sprintf('%s_to_%s', $data['originalType'], $data['destinationType']);
    $converted = $this->assetData->get($data['assetID'], $convert_key);
    // Asset has already been converted from the source type to the desired
    // type. This should have already been checked before queuing the item, but
    // is still a safe fail state.
    if ($converted) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Process the initial download request.
   *
   * @param array $data
   *   A keyed array with the following values:
   *     assetID: The asset ID.
   *     folderID: The folder ID to limit the check to.
   *     filename: The name of the file.
   *     originalType: The original file type.
   *     destinationType: The desired file type.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  protected function processStageQueueDownload(array $data) {

    // Construct the new filename based on the new extension.
    $filename_no_ext = pathinfo($data['filename'], PATHINFO_FILENAME);
    $data['destinationName'] = sprintf('%s.%s', $filename_no_ext, $data['destinationType']);

    // Check for an existing asset with the same destination location and name.
    $result = $this->damClient->searchAssets([
      'query' => sprintf('"%s"', $data['destinationName']),
      'limit' => 1,
      'folderid' => empty($data['folderID']) ? NULL : $data['folderID'],
    ]);
    if (!empty($result['total_count'])) {
      if (empty($data['folderID'])) {
        $this->logger
          ->warning('There was an existing file (@destinationName) when trying to convert @assetID (@filename).', [
            '@destinationName' => $data['destinationName'],
            '@filename' => $data['filename'],
            '@assetID' => $data['assetID'],
          ]);
      }
      else {
        $this->logger
          ->warning('There was an existing file (@destinationName) in the target folder (@folderID) when trying to convert @assetID (@filename).', [
            '@destinationName' => $data['destinationName'],
            '@filename' => $data['filename'],
            '@folderID' => $data['folderID'],
            '@assetID' => $data['assetID'],
          ]);
      }
      return;
    }

    // This kicks off the actual asset conversion process.
    $result = $this->damClient->queueAssetDownload($data['assetID'], [
      'format' => $data['destinationType'],
    ]);
    if (!empty($result['downloadKey'])) {
      $data['downloadKey'] = $result['downloadKey'];
      $data[$data['downloadKey']] = $result;
      $data['checks'] = 0;
      $data['stage'] = 'check_upload';
      // Requeue because we're not done checking for uploads, but don't throw
      // a RequeueException because we want to update the stored data.
      $this->convertQueue->createItem($data);
    }
  }

  /**
   * Process the upload stage of a queue item.
   *
   * @param array $data
   *   An array of queue item data. Should have the downloadKey values set.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  protected function processStageUpload(array $data) {

    if (empty($data['downloadKey'])) {
      $this->logger->error('Attempted to process a download for @assetID (@filename) with an empty download key.', [
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
      ]);
      return;
    }

    // We don't want to get stuck in a scenario where we keep checking for an
    // asset that will never (or takes too long) to succeed. This check lets us
    // abort processing for any assets that take multiple runs to complete.
    if (++$data['checks'] >= 10) {
      $this->logger->warning('Tried @checks times to get asset conversion for @assetID (@filename). Giving up.', [
        '@checks' => $data['checks'],
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
      ]);
      return;
    }

    $result = $this->damClient->downloadFromQueue($data['downloadKey']);
    // Top level state for a finished request is 'done' and not 'ready'.
    if (empty($result['status']) || $result['status'] != 'done') {
      $data[$data['downloadKey']] = $result;
      // Requeue our updated $data and check for the converted asset on the next
      // queue process.
      $this->convertQueue->createItem($data);
      return;
    }

    // The API is setup for multiple assets in a single batch, but only supports
    // sending one asset at this time.
    foreach ($result['assets'] as $current_asset) {
      // This should prevent breakage in the future if/when multiple assets are
      // included in the response.
      if (empty($current_asset['assetId']) || $current_asset['assetId'] != $data['assetID']) {
        continue;
      }
      // Individual asset finished states are 'ready' and not 'done'.
      elseif ('ready' == $current_asset['status']) {
        if (!empty($current_asset['presigned_url'])) {
          // We need to handle the upload in the same request since the URLs
          // may expire before the next time the queue is processed.
          $this->uploadAsset($data, $current_asset);
        }
        else {
          $this->logger->error('Conversion of asset @assetID (@filename) did not get a download URL. API issue?', [
            '@assetID' => $data['assetID'],
            '@filename' => $data['filename'],
          ]);
        }
      }
      elseif ('failed' == $current_asset['status']) {
        $this->logger->error('Conversion of asset @assetID (@filename) failed: @error', [
          '@assetID' => $data['assetID'],
          '@filename' => $data['filename'],
          '@error' => $current_asset['error_message'],
        ]);
      }
      elseif ('processing' == $current_asset['status']) {
        $this->convertQueue->createItem($data);
      }
      else {
        $this->logger->warning('Conversion of asset @assetID (@filename) unhandled status: @status', [
          '@assetID' => $data['assetID'],
          '@filename' => $data['filename'],
          '@status' => $current_asset['status'],
        ]);
      }
    }
  }

  /**
   * Transfer the converted asset back to Acquia DAM.
   *
   * @param array $data
   *   The original queue worker item data.
   *
   * @param array $downloadData
   *   The download data (presigned_url) for the converted asset.
   *
   * @throws \cweagans\webdam\Exception\UploadAssetException
   */
  protected function uploadAsset(array $data, array $downloadData) {

    if ($data['assetID'] != $downloadData['assetId']) {
      $this->logger->warning('uploadAsset does not support mismatched asset IDs at this time.');
      return;
    }
    elseif (empty($downloadData['presigned_url'])) {
      $this->logger->error('uploadAsset was called without a presigned URL.');
      return;
    }

    $path = system_retrieve_file($downloadData['presigned_url'], \file_directory_temp(), FALSE, FILE_EXISTS_RENAME);
    if (FALSE === $path) {
      $this->logger->error('Unable to retrieve @assetID (@filename) for download and re-upload.', [
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
      ]);
      return;
    }

    $targetID = $this->damClient->uploadAsset($path, $data['destinationName'], $data['folderID']);
    if (empty($targetID) || intval($targetID) == 0) {
      $this->logger->error('Failed to upload asset @assetID (@filename -> @destinationName) to Acquia DAM.', [
        '@assetID' => $data['assetID'],
        '@destinationName' => $data['destinationName'],
        '@filename' => $data['filename'],
      ]);
      return;
    }

    $data['targetID'] = intval($targetID);

    $this->logger->info('Uploaded @assetID (@filename) as @targetID (@destinationName) to Acquia DAM.', [
      '@assetID' => $data['assetID'],
      '@filename' => $data['filename'],
      '@destinationName' => $data['destinationName'],
      '@targetID' => $data['targetID'],
    ]);

    $data['stage'] = 'update_metadata';
    // Requeue with our updated data for metadata transfer.
    $this->convertQueue->createItem($data);
  }

  /**
   * Copy metadata from the original asset to the new one.
   *
   * @param array $data
   *   A keyed array that should have assetID and targetID set.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  protected function processStageUpdateMetadata(array $data) {

    if (empty($data['targetID'])) {
      $this->logger->error('Tried to migrate metadata for @assetID (@filename) but no target ID was set.', [
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
      ]);
      return;
    }

    $original_asset = $this->damClient->getAsset($data['assetID'], TRUE);
    if (empty($original_asset)) {
      $this->logger->error('Unable to get the original asset @assetID (@filename) to copy metadata into @targetID.', [
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
        '@targetID' => $data['targetID'],
      ]);
      return;
    }

    $new_asset = $this->damClient->editAsset($data['targetID'], [
      'description' => $original_asset->description,
      'status' => $original_asset->status,
    ]);
    if (FALSE === $new_asset) {
      $this->logger->warning('Failed to update asset @targetID (@destinationName) with properties from @assetID (@filename).', [
        '@assetID' => $data['assetID'],
        '@filename' => $data['filename'],
        '@targetID' => $data['targetID'],
        '@destinationName' => $data['destinationName'],
      ]);
    }

    if (!empty($original_asset->xmp_metadata)) {
      $new_metadata = [];
      foreach ($original_asset->xmp_metadata as $key => $values) {
        $new_metadata[$key] = $values['value'];
      }

      $response = $this->damClient->editAssetXmpMetadata($data['targetID'], $new_metadata);
      if (empty($response)) {
        $this->logger->warning('Failed to update asset @targetID (@destinationName) with XMP metadata from @assetID (@filename).', [
          '@assetID' => $data['assetID'],
          '@filename' => $data['filename'],
          '@targetID' => $data['targetID'],
          '@destinationName' => $data['destinationName'],
        ]);
      }
    }

    $this->logger->info('Successfully converted @assetID (@filename) to @targetID (@destinationName).', [
      '@assetID' => $data['assetID'],
      '@filename' => $data['filename'],
      '@targetID' => $data['targetID'],
      '@destinationName' => $data['destinationName'],
    ]);

    // Store the conversion success so we can exclude this asset in the future.
    $convert_key = sprintf('%s_to_%s', $data['originalType'], $data['destinationType']);
    $this->assetData->set($data['assetID'], $convert_key, TRUE);
  }

}
