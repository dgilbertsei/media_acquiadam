<?php

namespace Drupal\Tests\acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\acquiadam\Client;
use Drupal\Tests\acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;

/**
 * Client factory test.
 *
 * @group acquiadam
 */
class AcquiadamClientTest extends UnitTestCase {

  use AcquiadamConfigTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Acquia DAM client factory.
   *
   * @var \Drupal\acquiadam\Client
   */
  protected $client;

  /**
   * Checks if the service is created in the Drupal context.
   */
  public function testClient() {
    $this->assertNotNull(\Drupal::service('acquiadam.client'));
  }

  /**
   * Check if user is authenticated.
   */
  public function testCheckAuth() {
    $this->assertTrue($this->client->checkAuth());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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

    $client = new Client($http_client, $user_data, $current_user, $this->getConfigFactoryStub());

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
    $container->set('acquiadam.client', $client);
    $this->client = \Drupal::service('acquiadam.client');
  }

}
