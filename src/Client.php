<?php

namespace Drupal\media_acquiadam;

use cweagans\webdam\Client as OriginalClient;
use cweagans\webdam\Entity\Asset;
use cweagans\webdam\Exception\InvalidCredentialsException;
use Drupal;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

/**
 * Overridden implementation of the cweagans php-webdam-client.
 *
 * Adds support for refreshing OAuth sessions.
 */
class Client extends OriginalClient {

  /**
   * Contains the refresh token necessary to renew connections.
   *
   * @var string
   */
  protected $refreshToken;

  /**
   * Datastore for the active XMP fields.
   *
   * @var array
   */
  protected $activeXmpFields;

  /**
   * Get a list of metadata.
   *
   * @return array
   *   A list of active xmp metadata fields.
   */
  public function getActiveXmpFields() {
    if (!is_null($this->activeXmpFields)) {
      return $this->activeXmpFields;
    }

    try {
      $this->checkAuth();
    }
    catch (Exception $x) {
      Drupal::logger('media_acquiadam')->error(
          'Unable to authenticate to retrieve xmp field data.'
        );
      $this->activeXmpFields = [];
      return $this->activeXmpFields;
    }

    try {
      $response = $this->client->request(
        'GET',
        $this->baseUrl . '/metadataschemas/xmp?full=1',
        ['headers' => $this->getDefaultHeaders()]
      );
    }
    catch (Exception $x) {
      Drupal::logger('media_acquiadam')->error('Unable to get xmp field data.');
      $this->activeXmpFields = [];
      return $this->activeXmpFields;
    }

    $response = json_decode((string) $response->getBody());

    $this->activeXmpFields = [];
    foreach ($response->xmpschema as $field) {
      if ($field->status == 'active') {
        $this->activeXmpFields['xmp_' . strtolower($field->field)] = [
          'name' => $field->name,
          'label' => $field->label,
          'type' => $field->type,
        ];
      }
    }

    return $this->activeXmpFields;
  }

  /**
   * Authenticates with the DAM service and retrieves or uses an access token.
   *
   * {@inheritdoc}
   *
   * @return array
   *   An array of authentication token information.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   *
   * @see \Drupal\media_acquiadam\Client::getAuthState()
   */
  public function checkAuth() {

    $is_expired_token = empty($this->accessTokenExpiry) || time(
      ) >= $this->accessTokenExpiry;
    $is_expired_session = !empty($this->accessToken) && $is_expired_token;

    // Session is still valid.
    if (!empty($this->accessToken) && !$is_expired_token) {
      return $this->getAuthState();
    }

    // Session has expired but we have a refresh token.
    elseif ($is_expired_session && !empty($this->refreshToken)) {
      $data = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $this->refreshToken,
        'client_id' => $this->clientId,
        'client_secret' => $this->clientSecret,
      ];
      $this->authenticate($data);
    }
    // Session was manually set so we don't do anything.
    // Adding an $is_expired_session condition here allows the DAM browser to
    // fall back to the global account.
    elseif ($this->manualToken) {
      // @TODO: Why can't we authenticate after a manual set?
      throw new InvalidCredentialsException(
        'Cannot reauthenticate a manually set token.'
      );
    }
    // Expired or new session.
    else {
      $this->authenticate();
    }

    return $this->getAuthState();
  }

  /**
   * Get internal auth state details.
   *
   * {@inheritdoc}
   */
  public function getAuthState() {

    $state = parent::getAuthState();
    if (!empty($state['valid_token']) && empty($state['refresh_token'])) {
      $state['refresh_token'] = $this->refreshToken;
    }
    return $state;
  }

  /**
   * Authenticates a user.
   *
   * @param array $data
   *   An array of API parameters to pass. Defaults to password based
   *   authentication information.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function authenticate(array $data = []) {

    $url = $this->baseUrl . '/oauth2/token';
    if (empty($data)) {
      $data = [
        'grant_type' => 'password',
        'username' => $this->username,
        'password' => $this->password,
        'client_id' => $this->clientId,
        'client_secret' => $this->clientSecret,
      ];
    }

    // For error response body details:
    // @see \cweagans\webdam\tests\ClientTest::testInvalidClient().
    // @see \cweagans\webdam\tests\ClientTest::testInvalidGrant().
    // For successful auth response body details:
    // @see \cweagans\webdam\tests\ClientTest::testSuccessfulAuthentication().
    try {
      $response = $this->client->request(
        "POST",
        $url,
        ['form_params' => $data]
      );

      // Body properties: access_token, expires_in, token_type, refresh_token.
      $body = (string) $response->getBody();
      $body = json_decode($body);

      $this->accessToken = $body->access_token;
      $this->accessTokenExpiry = time() + $body->expires_in;
      // We should only get an initial refresh_token and reuse it after the
      // first session. The access_token gets replaced instead of a new
      // refresh_token.
      $this->refreshToken = !empty($body->refresh_token) ?
        $body->refresh_token : $this->refreshToken;
    }
    catch (ClientException $e) {
      // For bad auth, the WebDAM API has been observed to return either
      // 400 or 403, so handle those via InvalidCredentialsException.
      $status_code = $e->getResponse()->getStatusCode();
      if ($status_code == 400 || $status_code == 403) {
        $body = (string) $e->getResponse()->getBody();
        $body = json_decode($body);

        throw new InvalidCredentialsException(
          $body->error_description . ' (' . $body->error . ').'
        );
      }
      else {
        // We've received an error status other than 400 or 403; log it
        // and move on.
        Drupal::logger('media_acquiadam')->error(
          'Unable to authenticate. DAM API client returned a @code exception code with the following message: %message',
          [
            '@code' => $status_code,
            '%message' => $e->getMessage(),
          ]
        );
      }
    }
  }

  /**
   * Set the internal auth token.
   *
   * {@inheritdoc}
   *
   * @param string $token
   *   The token to set.
   * @param int $token_expiry
   *   The time when the token expires.
   * @param string $refresh_token
   *   The refresh token to set.
   */
  public function setToken($token, $token_expiry, $refresh_token = NULL) {

    parent::setToken($token, $token_expiry);
    $this->refreshToken = $refresh_token;
  }

  /**
   * Uploads file to Webdam AWS S3.
   *
   * @param mixed $presignedUrl
   *   The presigned URL we got in previous step from AWS.
   * @param string $file_uri
   *   The file URI.
   * @param string $file_type
   *   The File Content Type.
   *
   * @return array
   *   Response Status 100 / 200
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  protected function uploadPresigned($presignedUrl, $file_uri, $file_type) {
    $this->checkAuth();

    $file = fopen($file_uri, 'r');
    $response = $this->client->request(
      "PUT",
      $presignedUrl,
      [
        'headers' => ['Content-Type' => $file_type],
        'body' => stream_get_contents($file),
        RequestOptions::TIMEOUT => 0,
      ]
    );

    return [
      'status' => json_decode($response->getStatusCode(), TRUE),
    ];

  }

  /**
   * Queue custom asset conversions for download.
   *
   * This is a 2 step process:
   *   1. Queue assets.
   *   2. Download From Queue.
   *
   * This step will allow users to queue an asset for download by specifying an
   * AssetID and a Preset ID or custom conversion parameters. If a valid
   * PresetID is defined, the other conversions parameters will be ignored
   * (format, resolution, size, orientation, colorspace).
   *
   * @param array|int $assetIDs
   *   A single or list of asset IDs.
   * @param array $options
   *   Asset preset or conversion options.
   *
   * @return array
   *   An array of response data.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function queueAssetDownload($assetIDs, array $options) {
    $this->checkAuth();

    if (!is_array($assetIDs)) {
      $assetIDs = [$assetIDs];
    }

    $data = ['items' => []];
    foreach ($assetIDs as $assetID) {
      $data['items'][] = ['id' => $assetID] + $options;
    }

    $response = $this->client->request(
      'POST',
      $this->baseUrl . '/assets/queuedownload',
      [
        'headers' => $this->getDefaultHeaders(),
        RequestOptions::JSON => $data,
      ]
    );
    $response = json_decode((string) $response->getBody(), TRUE);

    return $response;
  }

  /**
   * Gets asset download queue information.
   *
   * This is a 2 step process:
   *   1. Queue assets.
   *   2. Download From Queue.
   *
   * This step will allow users to download the queued asset using the download
   * key returned from step1 (Queue asset process). The output of this step will
   * be a download URL to the asset or the download status, if the asset is not
   * ready for download.
   *
   * @param string $downloadKey
   *   The download key to check the status of.
   *
   * @return array
   *   An array of response data.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function downloadFromQueue($downloadKey) {
    $this->checkAuth();

    $response = $this->client->request(
      'GET',
      $this->baseUrl . '/downloadfromqueue/' . $downloadKey,
      ['headers' => $this->getDefaultHeaders()]
    );

    $response = json_decode((string) $response->getBody(), TRUE);

    return $response;
  }

  /**
   * Edit an asset.
   *
   * If an asset is uploaded and its required fields are not filled in, the
   * asset is in onhold status and cannot be activated until all required fields
   * are supplied. Any attempt to change the status to 'active' for assets that
   * still require metadata will return back 409.
   *
   * @param int $assetID
   *   The asset to edit.
   * @param array $data
   *   An array of values to set.
   *    filename       string  The new filename for the asset.
   *    status         string  The new status of the asset. Either active or
   *                           inactive.
   *    name           string  The new name for the asset.
   *    description    string  The new description of the asset.
   *    folder         long    The id of the folder to move asset to.
   *    thumbnail_ttl  string  Time to live for thumbnails
   *                             Default: Set by the account admin
   *                             Values: '+3 min', '+15 min', '+2 hours',
   *                             '+1 day', '+2 weeks', 'no-expiration'.
   *
   * @return \cweagans\webdam\Entity\Asset|bool
   *   An asset object on success, or FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function editAsset($assetID, array $data) {
    $this->checkAuth();

    $response = $this->client->request(
      'PUT',
      $this->baseUrl . '/assets/' . $assetID,
      [
        'headers' => $this->getDefaultHeaders(),
        RequestOptions::JSON => $data,
      ]
    );

    if (409 == $response->getStatusCode()) {
      return FALSE;
    }

    $asset = Asset::fromJson((string) $response->getBody());

    return $asset;
  }

  /**
   * Edit asset XMP metadata.
   *
   * @param int $assetID
   *   The asset to edit XMP metadata for.
   * @param array $data
   *   A key value array of metadata to edit.
   *
   * @return array
   *   The metadata of the asset.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function editAssetXmpMetadata($assetID, array $data) {
    $this->checkAuth();

    $data['type'] = 'assetxmp';

    $response = $this->client->request(
      'PUT',
      $this->baseUrl . '/assets/' . $assetID . '/metadatas/xmp',
      [
        'headers' => $this->getDefaultHeaders(),
        RequestOptions::JSON => $data,
      ]
    );

    $response = json_decode((string) $response->getBody(), TRUE);

    return $response;
  }

  /**
   * Returns the list of recent Webdam REST API "Notifications".
   *
   * @param array $query_options
   *   The associative array of optional query parameters:
   *   - "limit" - the maximum number of items to return;
   *   - "offset" - the starting position for the number of items to return;
   *   - "starttime" - the lowest (inclusive) "date_created_unix" notification
   *     property to return;
   *   - "endtime" - the highest (exclusive) "date_created_unix" notification
   *     property to return.
   *
   * @return array
   *   The response (associative array) from Notifications API containing the
   *   following keys:
   *   - "last_read" - date/time the Notifications API was last read;
   *   - "offset" - duplicates the offset parameter;
   *   - "limit" - duplicates the limit parameter;
   *   - "total" - the total number of notification items in the API;
   *   - "notifications" - notification items.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \cweagans\webdam\Exception\InvalidCredentialsException
   */
  public function getNotifications(array $query_options = []): array {
    $this->checkAuth();
    $query_options += [
      'limit' => 100,
      'offset' => 0,
      'starttime' => NULL,
      'endtime' => NULL,
    ];

    $response = $this->client->request(
      'GET',
      $this->baseUrl . '/notifications',
      [
        'headers' => $this->getDefaultHeaders(),
        'query' => $query_options,
      ]
    );

    return json_decode((string) $response->getBody(), TRUE);
  }

}
