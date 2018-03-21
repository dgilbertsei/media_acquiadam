<?php

/**
 * @file
 * Overridden implementation of the cweagans php-webdam-client to add support
 * for refreshing OAuth sessions.
 */

namespace Drupal\media_acquiadam;

use cweagans\webdam\Client as OriginalClient;
use cweagans\webdam\Exception\InvalidCredentialsException;
use GuzzleHttp\Exception\ClientException;

class Client extends OriginalClient {

  /** @var string Contains the refresh token necessary to renew connections. */
  protected $refreshToken;

  /**
   * Authenticates with the DAM service and retrieves an access token, or uses
   * existing one.
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

    /** @var bool TRUE if the access token expiration time has elapsed. */
    $is_expired_token = empty($this->accessTokenExpiry) || time() >= $this->accessTokenExpiry;
    /** @var bool $is_expired_session TRUE if the session has expired. */
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
      throw new InvalidCredentialsException('Cannot reauthenticate a manually set token.');
    }
    // Expired or new session.
    else {
      $this->authenticate();
    }

    return $this->getAuthState();
  }

  /**
   * Set the internal auth token.
   *
   * {@inheritdoc}
   *
   * @param string $token
   * @param int $token_expiry
   * @param string $refresh_token
   */
  public function setToken($token, $token_expiry, $refresh_token = NULL) {

    parent::setToken($token, $token_expiry);
    $this->refreshToken = $refresh_token;
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

    /**
     * For error response body details:
     *
     * @see \cweagans\webdam\tests\ClientTest::testInvalidClient()
     * @see \cweagans\webdam\tests\ClientTest::testInvalidGrant()
     *
     * For successful auth response body details:
     * @see \cweagans\webdam\tests\ClientTest::testSuccessfulAuthentication()
     */
    try {
      $response = $this->client->request("POST", $url, ['form_params' => $data]);

      // Body properties: access_token, expires_in, token_type, refresh_token
      $body = (string) $response->getBody();
      $body = json_decode($body);

      $this->accessToken = $body->access_token;
      $this->accessTokenExpiry = time() + $body->expires_in;
      // We should only get an initial refresh_token and reuse it after the
      // first session. The access_token gets replaced instead of a new
      // refresh_token.
      $this->refreshToken = !empty($body->refresh_token) ?
        $body->refresh_token :
        $this->refreshToken;
    } catch (ClientException $e) {
      // Looks like any form of bad auth with Webdam is a 400, but we're wrapping
      // it here just in case.
      if ($e->getResponse()->getStatusCode() == 400) {
        $body = (string) $e->getResponse()->getBody();
        $body = json_decode($body);

        throw new InvalidCredentialsException($body->error_description . ' (' . $body->error . ').');
      }
    }
  }

}
