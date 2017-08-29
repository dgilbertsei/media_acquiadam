<?php

/**
 * Abstract class base for Webdam assets.
 *
 * @package Webdam
 */
abstract class Webdam_Assets_AbstractAsset implements ArrayAccess, JsonSerializable {

  /**
   * A list of class dependencies.
   *
   * @var array
   */
  protected $depends = [];
  /**
   * Asset information as returned by the API.
   *
   * @var array
   */
  protected $asset = [];
  /**
   * The asset ID.
   *
   * @var int
   */
  protected $assetId = NULL;

  /**
   * Get the base used for API calls.
   *
   * @return string
   *   The base to use when building API urls.
   */
  abstract protected function getEndpointBase();

  /**
   * Create an Asset.
   *
   * Can load an asset direct from the API when given an ID, or pre-populate the
   * asset if given an array of asset informatino to use.
   *
   * @param int|array $assetId
   *   The asset ID or an array of asset information.
   * @param array $depends
   *   An array of class dependencies.
   */
  public function __construct($assetId = NULL, array $depends = []) {

    $this->depends = $depends;

    if (is_numeric($assetId)) {
      $this->setId($assetId);
    }
    // Allow priming the object without calling get() in case we already have
    // a result we want to use.
    elseif (is_array($assetId) && !empty($assetId['id'])) {
      $this->asset = $assetId;
      $this->setId($assetId['id'], FALSE);
    }
    elseif (!empty($assetId)) {
      throw new InvalidArgumentException('Constructor was given bad assetId data.');
    }
  }

  /**
   * Client request wrapper.
   *
   * A wrapper is necessary because a call to getClient() results in an API
   * request immediately. We want the API to be somewhat useable when loading
   * stored data without having to make API requests.
   *
   * @param string $endpoint
   *   The API endpoint to request.
   * @param string|array $data
   *   The data to send with the request.
   * @param array $headers
   *   Any additional headers to include.
   * @param string $method
   *   The request type.
   *
   * @return array
   *   The result of the API request.
   */
  protected function request($endpoint, $data = NULL, array $headers = [], $method = 'GET') {
    if (empty($this->depends['client'])) {
      $this->depends['client'] = Webdam_API::getClient();
    }
    return $this->depends['client']->request($endpoint, $data, $headers, $method);
  }

  /**
   * Make sure the given ID is valid to be used as an asset ID.
   *
   * @param int $id
   *   The ID to check.
   *
   * @return int|false
   *   Returns the asset ID or FALSE on failure.
   */
  protected function ensureIsValidAssetId($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT, [
      'options' => [
        'default' => 0,
        'min_range' => 1,
      ],
    ]);

    return $id;
  }

  /**
   * Ensure we have an asset ID set.
   *
   * Used when we want to ensure we're working with an ID and were not given an
   * array of data instead.
   *
   * @throws BadMethodCallException
   */
  protected function requireId() {
    if (is_null($this->assetId)) {
      throw new BadMethodCallException('No asset ID has been set.');
    }
  }

  /**
   * Set the asset ID for the current asset.
   *
   * @param int $assetId
   *   The asset ID to set.
   * @param bool $fetchAsset
   *   TRUE to fetch the asset after setting.
   *
   * @throws BadMethodCallException
   * @throws BadMethodCallException
   */
  public function setId($assetId, $fetchAsset = TRUE) {
    if (!empty($this->assetId)) {
      throw new BadMethodCallException('Unable to set asset ID once already set.');
    }

    $this->assetId = $this->ensureIsValidAssetId($assetId);
    if (FALSE === $this->assetId) {
      throw new InvalidArgumentException(sprintf('Invalid asset ID given: %d', $assetId));
    }

    if ($fetchAsset) {
      $this->get();
    }
  }

  /**
   * Fetch the asset from the API.
   *
   * @param bool $refresh
   *   TRUE to force a remote refresh if the asset has already been loaded.
   *
   * @return array|false
   *   The request response or FALSE on failure.
   */
  public function get($refresh = FALSE) {

    $this->requireId();

    if ($refresh || empty($this->asset)) {
      $result = $this->request(sprintf('%s/%d', $this->getEndpointBase(), $this->assetId));
      if (!empty($result) && is_array($result)) {
        $this->asset = $result;
      }
    }

    return isset($result) ? $result : $this->asset;
  }

  /**
   * Get the asset download URL.
   *
   * @param array $options
   *   An array of options to pass to the API call.
   *
   * @return array|false
   *   The request response or FALSE on failure.
   */
  public function getDownload(array $options = []) {

    $this->requireId();

    $options += [
      'sendNotificationsOff' => TRUE,
      'trackDownloadsOff' => TRUE,
      'geturl' => FALSE,
    ];

    return $this->request(sprintf('%s/%d/download', $this->getEndpointBase(), $this->asset['id']), $options);
  }

  /**
   * Get the asset download URL.
   *
   * @return string|false
   *   The asset download URL or FALSE on failure.
   */
  public function getDownloadUrl() {

    $result = $this->getDownload(['geturl' => TRUE]);

    return empty($result['url']) ? FALSE : $result['url'];
  }

  /**
   * Get multiple assets at once.
   *
   * @param array $assetIds
   *   An array of assets ID to fetch.
   *
   * @return array|false
   *   The request response or FALSE on failure.
   */
  public function getMultiple(array $assetIds) {

    $assetIds = array_filter($assetIds, function ($assetId) {
      return $this->ensureIsValidAssetId($assetId) !== FALSE;
    });
    $assetIds = array_filter($assetIds);

    $data = ['ids' => implode(',', $assetIds)];
    return $this->request(sprintf('%s/list', $this->getEndpointBase()), $data);
  }

  /**
   * Gets the path to this asset within the DAM web interface.
   *
   * @return string
   *   The URL to the asset within the DAM web interface.
   */
  public function getDAMPath() {

    $this->get();

    return sprintf('cloud/#folder/%d#%d', $this->asset['folder']['id'], $this->asset['id']);
  }

  /**
   * Implementation for ArrayAccess.
   *
   * Not implemented.
   *
   * {@inheritDoc}
   *
   * @throws BadMethodCallException
   */
  public function offsetSet($offset, $value) {
    throw new BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
  }

  /**
   * Implementation for ArrayAccess.
   *
   * Not implemented.
   *
   * {@inheritDoc}
   *
   * @throws BadMethodCallException
   */
  public function offsetUnset($offset) {
    throw new BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
  }

  /**
   * Implementation for ArrayAccess.
   *
   * Not implemented.
   *
   * {@inheritDoc}
   *
   * @return bool
   *   TRUE if the offset exists.
   */
  public function offsetExists($offset) {
    // Fetch the asset if it isn't already loaded.
    if (empty($this->asset)) {
      $this->get();
    }

    return isset($this->asset[$offset]);
  }

  /**
   * Implementation for ArrayAccess.
   *
   * Not implemented.
   *
   * {@inheritDoc}
   *
   * @return mixed
   *   The value of the key or NULL.
   */
  public function offsetGet($offset) {
    // Fetch the asset if it isn't already loaded.
    if (empty($this->asset)) {
      $this->get();
    }

    return isset($this->asset[$offset]) ?
      $this->asset[$offset] :
      NULL;
  }

  /**
   * Return a string representation of this asset.
   *
   * @return string
   *   The JSON encoded asset array.
   */
  public function __toString() {
    return drupal_json_encode($this->toArray());
  }

  /**
   * Get an array representation of this asset.
   *
   * @return array
   *   The asset as an array.
   */
  public function toArray() {
    return $this->get();
  }

  /**
   * Implementation for JsonSerializable.
   *
   * {@inheritDoc}
   */
  public function jsonSerialize() {
    return $this->toArray();
  }

}
