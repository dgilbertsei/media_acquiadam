<?php

namespace Drupal\acquiadam;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ClientFactory.
 *
 * Factory class for Client.
 */
class ClientFactory implements ContainerInjectionInterface {

  /**
   * A config object to retrieve Acquia DAM auth information from.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * A fully-configured Guzzle client to pass to the dam client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $guzzleClient;

  /**
   * A user data object to retrieve API keys from.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * ClientFactory constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config object to retrieve Acquia DAM auth information from.
   * @param \GuzzleHttp\ClientInterface $guzzleClient
   *   A fully configured Guzzle client to pass to the dam client.
   * @param \Drupal\user\UserDataInterface $userData
   *   A userdata object to retreive user-specific creds from.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The currently authenticated user.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $guzzleClient, UserDataInterface $userData, AccountProxyInterface $currentUser) {
    $this->config = $config_factory->get('acquiadam.settings');
    $this->guzzleClient = $guzzleClient;
    $this->userData = $userData;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('user.data'),
      $container->get('current_user')
    );
  }

  /**
   * Creates a new DAM client object.
   *
   * @param string $credentials
   *   The switch for which credentials the client object
   *   should be configured with.
   *
   * @return \Drupal\acquiadam\Client
   *   A configured DAM HTTP client object.
   */
  public function get($credentials = 'background') {
    $client = $this->getWithCredentials(
      $this->config->get('username'),
      $this->config->get('password'),
      $this->config->get('client_id'),
      $this->config->get('secret')
    );

    // Set the user's credentials in the client if necessary.
    if ($credentials == 'current') {
      $access_token = $this->userData->get(
        'acquiadam',
        $this->currentUser->id(),
        'acquiadam_access_token'
      );
      $access_token_expiration = $this->userData->get(
        'acquiadam',
        $this->currentUser->id(),
        'acquiadam_access_token_expiration'
      );
      $refresh_token = $this->userData->get(
        'acquiadam',
        $this->currentUser->id(),
        'acquiadam_refresh_token'
      );
      $client->setToken(
        $access_token,
        $access_token_expiration,
        $refresh_token
      );
    }

    return $client;
  }

  /**
   * Gets a base DAM Client object using the specified credentials.
   *
   * @param string $username
   *   The username to authenticate with.
   * @param string $password
   *   The password to authenticate with.
   * @param string $client_id
   *   The client ID to authenticate with.
   * @param string $secret
   *   The secret to authenticate with.
   *
   * @return \Drupal\acquiadam\Client
   *   The Acquia DAM client.
   *
   * @todo: Wildcat. Replace the guzzleClient provided to the Client with a REST client.
   */
  public function getWithCredentials($username, $password, $client_id, $secret) {
    return new Client(
      $this->guzzleClient,
      $username,
      $password,
      $client_id,
      $secret
    );
  }

}
