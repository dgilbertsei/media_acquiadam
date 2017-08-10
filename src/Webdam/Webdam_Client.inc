<?php

use OAuth2\Client as OAuth2Client;

/**
 * Client for making API requests.
 *
 * @package Webdam
 */
class Webdam_Client {

  /**
   * The base API URL.
   *
   * @var string
   */
  protected $apiBaseURL = 'https://apiv2.webdamdb.com';

  /**
   * The OAuth2 Client.
   *
   * @var \OAuth2\Client
   */
  protected $oauth2Client = NULL;

  /**
   * Construct a Webdam_Client object.
   *
   * @param \OAuth2\Client $oauth2Client
   *   A pre-configured OAuth2 Client object.
   */
  public function __construct(OAuth2Client $oauth2Client) {

    $this->oauth2Client = $oauth2Client;
    $this->getToken();
  }

  /**
   * Convert a URl and data into a normal URL.
   *
   * @param string $url
   *   The API URL.
   * @param array $data
   *   An array of data.
   *
   * @return string
   *   The API URL with query string parameters added.
   */
  protected function buildGetRequest($url, array $data) {
    if (!empty($data) && is_array($data)) {
      $data = drupal_http_build_query($data);
      $url .= '?' . $data;
    }

    return $url;
  }

  /**
   * Get the authroization token information.
   *
   * @return array
   *   An array of token information as returned by the OAuth2 client.
   */
  protected function getToken() {
    // Ensure we are authenticated.
    $this->oauth2Client->getAccessToken(FALSE);
    return $this->oauth2Client->token();
  }

  /**
   * Make a request to the Webdam API.
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
  public function request($endpoint, $data = NULL, array $headers = [], $method = 'GET') {

    $url = sprintf('%s/%s', $this->apiBaseURL, $endpoint);

    // Convert our data into a GET request.
    if ('GET' == drupal_strtoupper($method) && is_array($data)) {
      $url = $this->buildGetRequest($url, $data);
      $data = NULL;
    }

    $options = [
      'headers' => $headers,
      'method' => $method,
      'data' => $data,
    ];

    $auth_token = $this->getToken();
    if (empty($auth_token['access_token'])) {
      return FALSE;
    }
    $options['headers']['Authorization'] = sprintf('%s %s', ucfirst($auth_token['token_type']), $auth_token['access_token']);

    $resp = drupal_http_request($url, $options);
    $resp->data = drupal_json_decode($resp->data);

    // Webdam does not return proper error codes for different conditions, so
    // we can't do much beyond checking for "not a success".
    if (200 != $resp->code) {
      throw new UnexpectedValueException(sprintf('(%d) %s response when accessing %s', $resp->code, $resp->data['message'], $url));
    }

    return $resp->data;
  }

}
