<?php
/**
 * @file
 * Drush command implementations.
 */

namespace Drupal\media_acquiadam_convert\Commands;

use Drupal\Component\Utility\Unicode;
use Drush\Commands\DrushCommands;

/**
 * Media: Acquia DAM drush command file.
 */
class MediaAcquiadamConvertCommands extends DrushCommands {

  /**
   * Convert image assets from one type to another.
   *
   * @param $sourceExtension
   *   The source file type extension (ex: tiff).
   * @param $destinationExtension
   *   The destination file type extension (ex: png).
   * @param array $options An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @option clear
   *   Clear the existing queue of all items regardless of state.
   * @usage drush adtc tiff
   *   Queue all TIFFs to be converted to the default destination type.
   * @usage drush adtc tiff png
   *   Queue all TIFFs to be converted to PNG.
   * @usage drush adtc tiff png --clear
   *   Clear the queue of all pending items and then continue.
   *
   * @command acquiadam:type-convert
   * @aliases adtc,acquiadam-type-convert
   * @validate-module-enabled media_acquiadam
   */
  public function typeConvert($sourceExtension, $destinationExtension = 'jpg', array $options = ['clear' => NULL]) {
    $sourceExtension = Unicode::strtolower($sourceExtension);
    $destinationExtension = Unicode::strtolower($destinationExtension);

    $this->io()->title(dt('Media: Acquia DAM asset type conversion'));
    $this->io()
      ->text(dt('This will convert @source type assets to @dest. This process will duplicate each asset within Acquia DAM and may take a long time to finish.', [
        '@source' => $sourceExtension,
        '@dest' => $destinationExtension,
      ]));

    $queue = \Drupal::queue('media_acquiadam_asset_convert');
    if ($queue->numberOfItems() > 0) {

      $this->io()->text('');
      $this->io()
        ->text(dt('There are @count items still processing in the queue. The queue must be empty before attempting type conversion again.', [
          '@count' => $queue->numberOfItems(),
        ]));

      $this->io()->text('');
      $this->io()
        ->text(dt('The queue can be manually processed by running drush queue:run media_acquiadam_asset_convert'));
      $this->io()->text('');

      if (empty($options['clear'])) {
        $this->io()
          ->text(dt('You can force the queue to be cleared by using the --clear option, manually process the queue with drush, or wait for cron to finish processing the queue.'));
        return;
      }
    }

    if (!empty($options['clear'])) {
      $this->io()
        ->warning(\dt('All assets currently queued for processing will be removed. This will not delete already converted assets within Acquia DAM.'));
    }

    $choice = $this->io()->confirm(dt('Convert @source assets to @dest?', [
      '@source' => $sourceExtension,
      '@dest' => $destinationExtension,
    ]));
    if (empty($choice)) {
      $this->io()->text(dt('Aborted!'));
      return;
    }

    if (!empty($options['clear'])) {
      $queue->deleteQueue();
    }

    $assets_to_convert = $this->getAssetsByExtension($sourceExtension);

    $convert_key = sprintf('%s_to_%s', $sourceExtension, $destinationExtension);
    $converted = \Drupal::service('media_acquiadam.asset_data')
      ->get(NULL, $convert_key);

    foreach ($assets_to_convert as $assetID => $data) {

      if (!isset($converted[$assetID])) {
        $queue->createItem([
          'assetID' => intval($assetID),
          'folderID' => intval($data['folderID']),
          'filename' => $data['filename'],
          'originalType' => $sourceExtension,
          'destinationType' => $destinationExtension,
        ]);
      }
    }

    $msg = 'Added @count to the asset convert queue [@original -> @destination].';
    $msg_args = [
      '@count' => $queue->numberOfItems(),
      '@original' => $sourceExtension,
      '@destination' => $destinationExtension,
    ];

    $this->logger()->info($msg, $msg_args);
    \Drupal::logger('media_acquiadam_convert')->info($msg, $msg_args);
    $this->io()->success(\dt($msg, $msg_args));

    $this->io()
      ->text(dt('The queue can be manually processed by running drush queue:run media_acquiadam_asset_convert'));
  }

  /**
   * Get a list of assets based on their file extension.
   *
   * @param string $extension
   *   The file extension to filter by.
   * @param int $perPage
   *   The number of results to fetch per request.
   *
   * @return array
   *   An array keyed by asset IDs with the following values:
   *     assetID: The asset ID.
   *     folderID: The ID of the folder the asset is in.
   *     filename: The asset filename.
   *
   * @TODO: Move this into the Client class?
   */
  protected function getAssetsByExtension($extension, $perPage = 100) {

    $dam = media_acquiadam_convert_get_background_dam_client();

    $offset = 0;
    $total = NULL;

    $assets_with_ext = [];

    do {
      $result = $dam->searchAssets([
        'sortby' => 'datecreated',
        'sortdir' => 'asc',
        'types' => 'image',
        'query' => $extension,
        'limit' => $perPage,
        'offset' => $offset,
      ]);

      if (is_null($total) && !empty($result['total_count']) && intval($result['total_count']) > 0) {
        $total = intval($result['total_count']);
      }

      if (!empty($result['assets'])) {

        /** @var \cweagans\webdam\Entity\Asset $asset */
        foreach ($result['assets'] as $asset) {
          // Increment $offset even if the extension doesn't match up because the
          // offset is by item and not page and we don't want to miscount.
          $offset++;

          // We need to do a specific file extension check because we can't filter
          // the actual search by file extension, only a generic search for the
          // type.
          if (0 !== Unicode::strcasecmp($extension, $asset->filetype)) {
            continue;
          }

          $assets_with_ext[$asset->id] = [
            'assetID' => intval($asset->id),
            'folderID' => intval($asset->folder->id),
            'filename' => $asset->filename,
          ];
        }
      }
    } while ($offset < $total);

    return $assets_with_ext;
  }

}
