<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\media_acquiadam\Client;
use Drupal\media_acquiadam\ClientFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;

/**
 * Client factory test.
 *
 * @group media_acquiadam
 */
class AcquiadamClientFactoryTest extends UnitTestCase {

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Check to make sure that the 'background' option gives us a client.
   */
  public function testFactoryGetBackground() {
    $factory = $this->getClientFactory();
    $client = $factory->get('background');
    $this->assertInstanceOf(Client::class, $client);
  }

  /**
   * Gets an Acquia DAM Client Factory object.
   *
   * @return \Drupal\media_acquiadam\ClientFactory
   *   The Acquia DAM client factory service.
   */
  protected function getClientFactory() {
    $factory = ClientFactory::create(Drupal::getContainer(), [], '', []);
    return $factory;
  }

  /**
   * Check to make sure that the 'current' option gives us a client.
   */
  public function testFactoryGetCurrent() {
    $factory = $this->getClientFactory();
    $client = $factory->get('current');
    $this->assertInstanceOf(Client::class, $client);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigFactoryStub(array $configs = []) {
    return parent::getConfigFactoryStub([
      'media_acquiadam.settings' => [
        'username' => 'WDusername',
        'password' => 'WDpassword',
        'client_id' => 'WDclient-id',
        'secret' => 'WDsecret',
        'sync_interval' => '14400',
        'size_limit' => 1280,
      ],
    ]);
  }

  /**
   * Check to make sure that we can get a client directly.
   */
  public function testFactoryGetWithCredentials() {
    $factory = $this->getClientFactory();
    $client = $factory->getWithCredentials('nothing', 'nothing', 'nothing', 'nothing');
    $this->assertInstanceOf(Client::class, $client);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $current_user = $this->getMockBuilder(AccountProxyInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $http_client = $this->getMockBuilder(ClientInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $user_data = $this->getMockBuilder(UserDataInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation', $this->getStringTranslationStub());
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('http_client', $http_client);
    $this->container->set('user.data', $user_data);
    $this->container->set('current_user', $current_user);
    Drupal::setContainer($this->container);
  }

}
