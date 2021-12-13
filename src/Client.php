<?php

namespace Drupal\media_acquiadam;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_acquiadam\Entity\Asset;
use Drupal\media_acquiadam\Entity\Category;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the integration with Acquia DAM.
 */
class Client {

  /**
   * The Guzzle client to use for communication with the Acquia DAM API.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The base URL of the Acquia DAM API.
   *
   * @var string
   */
  protected $baseUrl = "https://api.widencollective.com/v2";

  /**
   * Datastore for the specific metadata fields.
   *
   * @var array
   */
  protected $specificMetadataFields;

  /**
   * The user data factory service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Drupal config factory.
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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The version of this client. Used in User-Agent string for API requests.
   *
   * @var string
   */
  const CLIENTVERSION = "2.x";

  /**
   * Client constructor.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle client interface.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data interface.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ClientInterface $client, UserDataInterface $user_data, AccountInterface $account, ConfigFactoryInterface $configFactory, RequestStack $request_stack) {
    $this->client = $client;
    $this->userData = $user_data;
    $this->account = $account;
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('media_acquiadam.settings');
    $this->requestStack = $request_stack;
  }

  /**
   * Check if the current user is authenticated on Acquia DAM.
   *
   * In case of anonymous user, check the generic token has been configured.
   *
   * @return bool
   *   TRUE if the authentication details are available. FALSE otherwise.
   */
  public function checkAuth(): bool {
    $request = $this->requestStack->getCurrentRequest();

    if ($this->account->isAuthenticated()) {
      $account = $this->userData->get('media_acquiadam', $this->account->id(), 'account');
      if (isset($account['acquiadam_username']) && isset($account['acquiadam_token'])) {
        return TRUE;
      }
    }
    elseif (!$this->account->isAuthenticated() && !empty($this->config->get('token'))
      && (PHP_SAPI === 'cli' || $request->attributes->get(RouteObjectInterface::ROUTE_NAME) === 'system.cron_settings')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get internal auth state details.
   *
   * @return array
   *   An array with the auth state details (username and token).
   */
  public function getAuthState(): array {
    $state = ['valid_token' => FALSE];

    $account = $this->userData->get('media_acquiadam', $this->account->id(), 'account');
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
   * @return array
   *   A list of headers to be used in API calls.
   */
  protected function getDefaultHeaders(): array {
    $token = NULL;
    $request = $this->requestStack->getCurrentRequest();

    if ($this->account->isAuthenticated()) {
      $account = $this->userData->get('media_acquiadam', $this->account->id(), 'account');
      if (isset($account['acquiadam_token'])) {
        $token = $account['acquiadam_token'];
      }
    }
    elseif (!$this->account->isAuthenticated() && !empty($this->config->get('token'))
      && (PHP_SAPI === 'cli' || $request->attributes->get(RouteObjectInterface::ROUTE_NAME) === 'system.cron_settings')) {
      $token = $this->config->get('token');
    }

    return [
      'User-Agent' => 'drupal/acquiadam ' . self::CLIENTVERSION,
      'Accept' => 'application/json',
      'Authorization' => 'Bearer ' . $token,
    ];
  }

  /**
   * Get a Category given a Category Name.
   *
   * @param string $categoryName
   *   The Acquia DAM Category Name.
   *
   * @return \Drupal\media_acquiadam\Entity\Category
   *   The category object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCategoryByName(string $categoryName): Category {
    $this->checkAuth();

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/categories/' . $categoryName,
      ['headers' => $this->getDefaultHeaders()]
    );

    $category = Category::fromJson((string) $response->getBody());

    return $category;
  }

  /**
   * Load subcategories by Category link or parts (used in breadcrumb).
   *
   * @param \Drupal\media_acquiadam\Entity\Category $category
   *   Category object.
   *
   * @return \Drupal\media_acquiadam\Entity\Category[]
   *   A list of sub-categories (ie: child categories).
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCategoryData(Category $category): array {
    $this->checkAuth();
    $url = $this->baseUrl . '/categories';
    // If category is not set, it will load the root category.
    if (isset($category->links->categories)) {
      $url = $category->links->categories;
    }
    elseif (!empty($category->parts)) {
      $cats = "";
      foreach ($category->parts as $part) {
        $cats .= "/" . $part;
      }
      $url .= $cats;
    }

    $response = $this->client->request(
      "GET",
      $url,
      ['headers' => $this->getDefaultHeaders()]
    );
    $category = Category::fromJson((string) $response->getBody());
    return $category;
  }

  /**
   * Get top level categories.
   *
   * @return Drupal\media_acquiadam\Entity\Category[]
   *   A list of top level categories (ie: root categories).
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getTopLevelCategories(): array {
    $this->checkAuth();

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/categories',
      ['headers' => $this->getDefaultHeaders()]
    );

    $categories_data = json_decode($response->getBody());

    $categories = [];
    foreach ($categories_data->items as $category) {
      $category->items = $this->getCategoryByName($category->name);
      $categories[] = Category::fromJson($category);
    }

    return $categories;
  }

  /**
   * Get an Asset given an Asset ID.
   *
   * @param string $assetId
   *   The Acquia DAM Asset ID.
   * @param array $expands
   *   The additional properties to be included.
   *
   * @return \Drupal\media_acquiadam\Entity\Asset
   *   The asset entity.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAsset(string $assetId, array $expands = []): ?Asset {
    $this->checkAuth();

    $required_expands = Asset::getRequiredExpands();
    $allowed_expands = Asset::getAllowedExpands();
    $expands = array_intersect(array_unique($expands + $required_expands), $allowed_expands);

    $asset = NULL;
    try {
      $response = $this->client->request(
        "GET",
        $this->baseUrl . '/assets/' . $assetId . '?expand=' . implode('%2C', $expands),
        ['headers' => $this->getDefaultHeaders()]
      );

      $asset = Asset::fromJson((string) $response->getBody());
    }
    catch (\Exception $e) {
      \Drupal::logger('media_acquiadam')->error('Unable to retrieve asset %asset_id. Exception message: %message', [
        '%asset_id' => $assetId,
        '%message' => $e->getMessage(),
      ]);
    }

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
   * @throws \Drupal\media_acquiadam\Exception\InvalidCredentialsException
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
   * @return string
   *   Acquia DAM response (asset id).
   *
   * @throws UploadAssetException
   *   If uploadAsset fails we throw an instance of UploadAssetException
   *   that contains a message for the caller.
   */
  public function uploadAsset(string $file_uri, string $file_name, int $folderID): string {
    $this->checkAuth();

    // Getting file data from file_uri.
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
   * Get a list of Assets given a Category ID.
   *
   * @param string $category_name
   *   Category name.
   * @param array $params
   *   Additional query parameters for the request.
   *
   * @return array
   *   Contains the following keys:
   *     - total_count: The total number of assets in the result set across all
   *       pages.
   *     - assets: an array of Asset objects.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAssetsByCategory(string $category_name, array $params = []): array {
    if ($category_name) {
      $params['query'] = 'category:' . $category_name;
    }

    // Fetch all assets of current category.
    $assets = $this->searchAssets($params);

    return $assets;
  }

  /**
   * Get a list of Assets given an array of Asset ID's.
   *
   * @param array $assetIds
   *   The Acquia DAM Asset ID's.
   * @param array $expand
   *   A list of dta items to expand on the result set.
   *
   * @return array
   *   A list of assets.
   */
  public function getAssetMultiple(array $assetIds, array $expand = []): array {
    $this->checkAuth();

    if (empty($assetIds)) {
      return [];
    }

    $assets = [];
    foreach ($assetIds as $assetId) {
      $assets[] = $this->getAsset($assetId, $expand);
    }
    return $assets;
  }

  /**
   * Search for assets using the Acquia DAM search API.
   *
   * @param array $params
   *   An array used as query parameter. Valid parameters are documented on
   *   https://widenv2.docs.apiary.io/#reference/assets/assets/list-by-search-query.
   *
   * @return array
   *   A list of assets.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function searchAssets(array $params): array {
    $this->checkAuth();

    $date = date('m/d/Y');
    $params['query'] = $params['query'] ? $params['query'] . ' AND ' : '';
    $params['query'] .= 'rd:([before ' . $date . '] OR [' . $date . ']) AND ed:((isEmpty) OR [after ' . $date . '])';

    $response = $this->client->request(
      "GET",
      $this->baseUrl . '/assets/search',
      [
        'headers' => $this->getDefaultHeaders(),
        'query' => $params,
      ]
    );
    $response = json_decode((string) $response->getBody());
    $results = [
      'total_count' => $response->total_count,
    ];
    foreach ($response->items as $asset) {
      $results['assets'][] = Asset::fromJson($asset);
    }
    return $results;
  }

  /**
   * Download file asset from Acquia DAM.
   *
   * @param string $assetID
   *   Asset ID to be fetched.
   *
   * @return string
   *   Contents of the file as a string.
   */
  public function downloadAsset(string $assetID): string {
    $this->checkAuth();

    $response = $this->getAsset($assetID);
    $file_content = file_get_contents(str_replace('&download=true', '', $response->embeds->original->url));

    return $file_content;
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
   * @throws \Drupal\media_acquiadam\Exception\InvalidCredentialsException
   */
  public function queueAssetDownload($assetIDs, array $options): array {
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
   * @throws \Drupal\media_acquiadam\Exception\InvalidCredentialsException
   */
  public function downloadFromQueue($downloadKey): array {
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
   * @param string $assetID
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
   * @return \Drupal\media_acquiadam\Entity\Asset|bool
   *   An asset object on success, or FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\media_acquiadam\Exception\InvalidCredentialsException
   */
  public function editAsset(string $assetID, array $data) {
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
   * Get a list of metadata.
   *
   * @return array
   *   A list of metadata fields.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getSpecificMetadataFields(): array {
    if (!empty($this->specificMetadataFields)) {
      return $this->specificMetadataFields;
    }

    try {
      $this->checkAuth();
    }
    catch (\Exception $e) {
      \Drupal::logger('media_acquiadam')->error('Unable to authenticate to retrieve metadata fields. Exception message: %message', [
        '%message' => $e->getMessage(),
      ]);
      $this->specificMetadataFields = [];
      return $this->specificMetadataFields;
    }

    try {
      $response = $this->client->request(
        'GET',
        'https://' . $this->config->get('domain') . '/api/rest/metadata/types',
        [
          'headers' => $this->getDefaultHeaders(),
        ]
      );

    }
    catch (\Exception $e) {
      \Drupal::logger('media_acquiadam')->error('Unable to retrieve metadata fields. Exception message: %message', [
        '%message' => $e->getMessage(),
      ]);
      $this->specificMetadataFields = [];
      return $this->specificMetadataFields;
    }

    $response = json_decode((string) $response->getBody());

    $this->specificMetadataFields = [];
    foreach ($response->types as $type) {
      foreach ($type->fields as $field) {
        switch ($field->discriminator) {
          case 'TextArea':
            $type = 'text_long';
            break;

          case 'Date':
            $type = 'datetime';
            break;

          default:
            $type = 'string';
        }
        $this->specificMetadataFields[$field->displayKey] = [
          'label' => $field->displayName,
          'type' => $type,
        ];
      }
    }

    return $this->specificMetadataFields;
  }

  /**
   * Register integration link on Acquia DAM via API.
   *
   * @param array $data
   *   The body of the POST request.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function registerIntegrationLink(array $data) {
    try {
      $this->checkAuth();
    }
    catch (\Exception $e) {
      \Drupal::logger('media_acquiadam')->error('Unable to authenticate to register integration link for asset @uuid. Exception message: %message', [
        '@uuid' => $data['assetUuid'],
        '%message' => $e->getMessage(),
      ]);
      return FALSE;
    }

    try {
      $response = $this->client->request(
        'POST',
        'https://' . $this->config->get('domain') . '/api/rest/integrationlink',
        [
          'headers' => $this->getDefaultHeaders(),
          RequestOptions::JSON => $data,
        ]
      );

      $response = json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      \Drupal::logger('media_acquiadam')->error('Unable to register integration link for asset @uuid. Exception message: %message', [
        '@uuid' => $data['assetUuid'],
        '%message' => $e->getMessage(),
      ]);
      return FALSE;
    }

    return $response;
  }

  /**
   * Get all the integration links which have been registered on Acquia DAM.
   *
   * @return array
   *   All the integration links which are registered on Acquia DAM.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getIntegrationLinks(): array {
    $this->checkAuth();

    $response = $this->client->request(
      'GET',
      'https://' . $this->config->get('domain') . '/api/rest/integrationlink',
      [
        'headers' => $this->getDefaultHeaders(),
      ]
    );

    $response = json_decode((string) $response->getBody(), TRUE);

    return $response->integrationLinks;
  }

  /**
   * Get a specific integration link by its uuid.
   *
   * @param string $uuid
   *   The uuid of the integration link to fetch.
   *
   * @return mixed
   *   The integration link if found, NULL otherwise.
   */
  public function getIntegrationLink(string $uuid) {
    foreach ($this->getIntegrationLinks() as $link) {
      if ($link->uuid === $uuid) {
        return $link;
      }
    }

    return NULL;
  }

  /**
   * Get all the integration links which have been registered for an asset.
   *
   * @param string $asset_uuid
   *   The uuid of the asset to check.
   *
   * @return array
   *   The integration links of the asset.
   */
  public function getAssetIntegrationLinks(string $asset_uuid): array {
    $links = [];

    foreach ($this->getIntegrationLinks() as $link) {
      if ($link->assetUuid === $asset_uuid) {
        $links[] = $link;
      }
    }

    return $links;
  }

}
