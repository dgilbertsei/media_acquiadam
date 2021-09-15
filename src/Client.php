<?php
/**
 * @file
 * @todo: Wildcat. Replace this client with the new service's client.
 */
namespace Drupal\acquiadam;

use Drupal\acquiadam\Entity\Asset;
use Drupal\acquiadam\Entity\Folder;
use Drupal\acquiadam\Entity\MiniFolder;
use Drupal\acquiadam\Entity\User;
use Drupal\acquiadam\Exception\InvalidCredentialsException;
use Drupal\acquiadam\Exception\UploadAssetException;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserData;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

/**
 * Overridden implementation of the CweagansClient.
 *
 * Adds support for refreshing OAuth sessions.
 */
class Client {

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
   * The version of this client. Used in User-Agent string for API requests.
   *
   * @var string
   */
  const CLIENTVERSION = "1.x-dev";

  /**
   * The Guzzle client to use for communication with the Acquia DAM API.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The base URL of the Acquia DAM API.
   */
  protected $baseUrl = "https://api.widencollective.com/v2";

  /**
   * The user data factory service.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Client constructor.
   *
   * @param \GuzzleHttp\ClientInterface $client
   * @param UserData $user_data
   * @param \Drupal\Core\Session\AccountInterface $account
   */
  public function __construct(ClientInterface $client, UserData $user_data, AccountInterface $account) {
    $this->client = $client;
    $this->userData = $user_data;
    $this->account = $account;
  }

  /**
   * Authenticates with the Acquia DAM service and retrieves an access token, or uses existing one.
   */
  public function checkAuth() {
    $account = $this->userData->get('acquiadam', $this->account->id(), 'account');

    if (!isset($account['acquiadam_username']) || !isset($account['acquiadam_token'])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get internal auth state details.
   *
   * {@inheritdoc}
   */
  public function getAuthState() {
    $state = ['valid_token' => FALSE];

    $account = $this->userData->get('acquiadam', $this->account->id(), 'account');
    if (isset($account['acquiadam_username']) || isset($account['acquiadam_token'])) {
      $state = [
        'valid_token' => TRUE,
        'username' => $account['acquiadam_username'],
        'access_token' => $account['acquiadam_token'],
      ];
    }

    return $state;
  }

  /**
   * Return an array of headers to add to every authenticated request.
   *
   * Note that this should not be used for the initial authentication request, as
   * it will attempt to add an access token that we don't have yet.
   *
   * @return array
   */
  protected function getDefaultHeaders() {
    $account = $this->userData->get('acquiadam', $this->account->id(), 'account');

    return [
      'User-Agent' => 'drupal/acquiadam ' . self::CLIENTVERSION,
      'Accept' => 'application/json',
      'Authorization' => 'Bearer ' . $account['acquiadam_token'],
    ];
  }

  /**
   * Get a list of metadata.
   *
   * @return array
   *   A list of active xmp metadata fields.
   */
  /*public function getActiveXmpFields() {
    if (!is_null($this->activeXmpFields)) {
      return $this->activeXmpFields;
    }

    try {
      $this->checkAuth();
    }
    catch (\Exception $x) {
      \Drupal::logger('acquiadam')->error(
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
    catch (\Exception $x) {
      \Drupal::logger('acquiadam')->error('Unable to get xmp field data.');
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
  }*/

  /**
   * Get subscription details for the account.
   *
   * @todo Should this be an Entity?
   *
   * @return \stdClass
   *   Returns a stdClass with the following properties:
   *    - maxAdmins
   *    - numAdmins
   *    - maxContributors
   *    - numContributors
   *    - maxEndUsers
   *    - maxUsers
   *    - url
   *    - username
   *    - planDiskSpace
   *    - activeUsers
   *    - inactiveUsers
   */
  /*public function getAccountSubscriptionDetails() {
    $this->checkAuth();

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/subscription',
      ['headers' => $this->getDefaultHeaders()]
    );

    $account = json_decode($response->getBody());

    return $account;
  }*/

  /**
   * Get a Folder given a Folder ID.
   *
   * @param int $folderID
   *   The Acquia DAM Folder ID.
   *
   * @return Folder
   */
  /*public function getFolder($folderID) {
    $this->checkAuth();

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/folders/' . $folderID,
      ['headers' => $this->getDefaultHeaders()]
    );

    $folder = Folder::fromJson((string) $response->getBody());

    return $folder;
  }*/

  /**
   * Get top level folders.
   *
   * @return Folder[]
   */
  /*public function getTopLevelFolders() {
    $this->checkAuth();

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/folders/0',
      ['headers' => $this->getDefaultHeaders()]
    );

    $folder_data = json_decode($response->getBody());

    $folders = [];
    foreach ($folder_data as $folder) {
      $folders[] = Folder::fromJson($folder);
    }

    return $folders;
  }*/

  /**
   * Get an Asset given an Asset ID.
   *
   * @param int $assetId
   *   The Acquia DAM Asset ID.
   * @param array $expand
   *   The additional properties to be included.
   *
   * @return Asset
   */
  public function getAsset($assetId, $expand = []) {
    $this->checkAuth();

    $expand = array_intersect($expand, Asset::getAllowedExpands());

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/assets/' . $assetId . '?expand=' . implode(',', $expand),
      ['headers' => $this->getDefaultHeaders()]
    );

    $asset = Asset::fromJson((string) $response->getBody());

    return $asset;
  }

  /**
   * Gets presigned url from AWS S3.
   *
   * @param string $file_type
   *   The File Content Type.
   * @param string $file_name
   *   The File filename.
   * @param string $file_size
   *   The File size.
   * @param string $folderID
   *   The folder ID to upload the file to.
   *
   * @return mixed
   *   Presigned url needed for next step + PID.
   */
  protected function getPresignUrl($file_type, $file_name, $file_size, $folderID) {
    $this->checkAuth();

    $file_data = [
      'filesize' => $file_size,
      'filename' => $file_name,
      'contenttype' => $file_type,
      'folderid' => $folderID,
    ];
    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/ws/awss3/generateupload',
      [
        'headers' => $this->getDefaultHeaders(),
        'query' => $file_data,
      ]
    );

    return json_decode($response->getBody());
  }

  /**
   * Authenticates a user.
   *
   * @param array $data
   *   An array of API parameters to pass. Defaults to password based
   *   authentication information.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\acquiadam\Exception\InvalidCredentialsException
   */
  /*public function authenticate(array $data = []) {

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
    // @see \Drupal\acquiadam\tests\ClientTest::testInvalidClient().
    // @see \Drupal\acquiadam\tests\ClientTest::testInvalidGrant().
    // For successful auth response body details:
    // @see \Drupal\acquiadam\tests\ClientTest::testSuccessfulAuthentication().
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
      // For bad auth, the Acquia DAM API has been observed to return either
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
        \Drupal::logger('acquiadam')->error(
          'Unable to authenticate. DAM API client returned a @code exception code with the following message: %message',
          [
            '@code' => $status_code,
            '%message' => $e->getMessage(),
          ]
        );
      }
    }
  }*/

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
  /*public function setToken($token, $token_expiry, $refresh_token = NULL) {
    $this->manualToken = TRUE;
    $this->accessToken = $token;
    $this->accessTokenExpiry = $token_expiry;
    $this->refreshToken = $refresh_token;
  }*/

  /**
   * Uploads file to Acquia DAM AWS S3.
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
   * @throws \Drupal\acquiadam\Exception\InvalidCredentialsException
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
   * Confirms the upload to Acquia DAM.
   *
   * @param string $pid
   *   The Process ID we got in first step.
   *
   * @return string
   *   The uploaded/edited asset ID.
   */
  protected function uploadConfirmed($pid) {
    $this->checkAuth();

    $response = $this->client->request(
      "PUT",
      $this->baseUrl . '/ws/awss3/finishupload/' . $pid,
      ['headers' => $this->getDefaultHeaders()]
    );

    return (string) json_decode($response->getBody(), TRUE)['id'];

  }

  /**
   * Uploads Assets to Acquia DAM using the previously defined methods.
   *
   * @param string $file_uri
   *   The file URI.
   * @param string $file_name
   *   The File filename.
   * @param int $folderID
   *   The Acquia DAM folder ID.
   *
   * @throws UploadAssetException
   *   If uploadAsset fails we throw an instance of UploadAssetException
   *   that contains a message for the caller.
   *
   * @return string
   *   Acquia DAM response (asset id).
   */
  public function uploadAsset($file_uri, $file_name, $folderID) {
    $this->checkAuth();

    //Getting file data from file_uri
    $file_type = mime_content_type($file_uri);
    $file_size = filesize($file_uri);

    $response = [];
    // Getting Pre-sign URL.
    $presign = $this->getPresignUrl($file_type, $file_name, $file_size, $folderID);

    if (property_exists($presign, 'presignedUrl')) {
      // Post-sign upload.
      $postsign = $this->uploadPresigned($presign->presignedUrl, $file_uri, $file_type);

      if ($postsign['status'] == '200' || $postsign['status'] == '100') {
        // Getting Asset ID.
        $response = $this->uploadConfirmed($presign->processId);
      }
      else {
        // If we got presignedUrl but upload not confirmed, we throw exception.
        throw new UploadAssetException('Failed to upload file after presigning.');
      }
    }
    else {
      // If we couldn't retrieve presignedUrl, we throw exception.
      throw new UploadAssetException('Failed to obtain presigned URL from AWS.');
    }
    return $response;
  }

  /**
   * Get a list of Assets given a Folder ID.
   *
   * @param int $folderId
   *   The Acquia DAM folder ID.
   *
   * @param array $params
   *   Additional query parameters for the request.
   *     - sortby: The field to sort by. Options: filename, filesize, datecreated, datemodified. (Default=datecreated)
   *     - sortdir: The direction to sort by. Options: asc, desc (Default=asc)
   *     - limit: The number of items to return. Any int between 1 and 100. (Default=50)
   *     - offset: The item number to start with. (Default=0)
   *     - types: File type filter. Options: image, audiovideo, document, presentation, other. (Default=NULL)
   *
   * @return object
   *   Contains the following keys:
   *     - folders: an array containing a MiniFolder describing $folderId
   *     - offset: The offset used for the query.
   *     - total_count: The total number of assets in the result set across all pages.
   *     - limit: The number of assets returned at a time.
   *     - facets: Information about the assets returned.
   *     - items: an array of Asset objects.
   */
  public function getFolderAssets($folderId, array $params =[]) {
    $this->checkAuth();


    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/folders/' . $folderId . '/assets',
      [
        'headers' => $this->getDefaultHeaders(),
        'query' => $params,
      ]
    );
    $response = json_decode((string) $response->getBody());

    // Replace items key with actual Asset objects.
    $assets = [];
    foreach ($response->items as $asset) {
      $assets[] = Asset::fromJson($asset);
    }
    $response->items = $assets;

    // Replace folders key with actual Folder objects.
    $folders = [];
    if(isset($response->folders) && is_array($response->folders)) {
      foreach ($response->folders as $folder) {
        $folders[] = MiniFolder::fromJson($folder);
      }
    }
    $response->folders = $folders;

    return $response;
  }

  /**
   * Get a list of Assets given an array of Asset ID's.
   *
   * @param array $assetIds
   *   The Acquia DAM Asset ID's.
   *
   * @return array
   */
  public function getAssetMultiple(array $assetIds) {
    $this->checkAuth();

    if (empty($assetIds)) {
      return [];
    }

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/assets/list?ids=' . implode(',',$assetIds),
      ['headers' => $this->getDefaultHeaders()]
    );
    $response = json_decode((string) $response->getBody());
    $assets = [];
    foreach ($response as $asset){
      $assets[] = Asset::fromJson($asset);
    }
    return $assets;
  }

  /**
   * Get a list of Assets given an array of Asset ID's.
   *
   * @param array $params
   *   Additional query parameters for the request.
   *     - sortby: The field to sort by. Options: filename, filesize, datecreated, datemodified. (Default=datecreated)
   *     - sortdir: The direction to sort by. Options: asc, desc (Default=asc)
   *     - limit: The number of items to return. Any int between 1 and 100. (Default=50)
   *     - offset: The item number to start with. (Default=0)
   *     - types: File type filter. Options: image, audiovideo, document, presentation, other. (Default=NULL)
   *
   * @return array
   *
   * @todo clean this up. mystery arrays make an api hard to use.
   */
  public function searchAssets(array $params) {
    $this->checkAuth();

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/search',
      [
        'headers' => $this->getDefaultHeaders(),
        'query' => $params,
      ]
    );
    $response = json_decode((string) $response->getBody());

    $results = [
      'total_count' => $response->total_count,
    ];
    foreach ($response->items as $asset){
      $results['assets'][] = Asset::fromJson($asset);
    }
    return $results;
  }

  /**
   * Download file asset from Acquia DAM
   *
   * @param int $assetID
   *   Asset ID to be fetched
   *
   * @return string
   *   Contents of the file as a string
   */
  public function downloadAsset($assetID) {
    $this->checkAuth();

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/assets/' . $assetID . '/download',
      [
        'headers' => $this->getDefaultHeaders(),
      ]
    );
    return $response->getBody();
  }

  /**
   * Get asset metadata.
   */
  public function getAssetMetadata($assetId) {
    $this->checkAuth();

    $response = $this->client->request(
      'GET',
      $this->baseUrl . '/assets/' . $assetId . '/metadatas/xmp',
      ['headers' => $this->getDefaultHeaders()]
    );

    $response = json_decode((string) $response->getBody());

    $metadata = [];
    foreach ($response->active_fields as $field) {
      if (!empty($field->value)) {
        $metadata[$field->field] = [
          'label' => $field->field_name,
          'value' => $field->value,
        ];
      }
    }

    return $metadata;
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
   * @throws \Drupal\acquiadam\Exception\InvalidCredentialsException
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
   * @throws \Drupal\acquiadam\Exception\InvalidCredentialsException
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
   * @return \Drupal\acquiadam\Entity\Asset|bool
   *   An asset object on success, or FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\acquiadam\Exception\InvalidCredentialsException
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
   * @throws \Drupal\acquiadam\Exception\InvalidCredentialsException
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
   * Returns the list of recent Acquia DAM REST API "Notifications".
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
   * @throws \Drupal\acquiadam\Exception\InvalidCredentialsException
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
    if ($response->getStatusCode() == 429) {
      \Drupal::logger('acquiadam')->error(
        'Failed to fetch asset ids: Too Many Requests.'
      );
      return [];
    }

    return json_decode((string) $response->getBody(), TRUE);
  }

}
