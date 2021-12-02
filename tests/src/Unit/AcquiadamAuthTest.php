<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\Messenger\Messenger;
use Drupal\media_acquiadam\AcquiadamAuthService;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Acquidam Auth test.
 *
 * @group media_acquiadam
 */
class AcquiadamAuthTest extends UnitTestCase {

  use AcquiadamConfigTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Acquia DAM Auth service.
   *
   * @var \Drupal\media_acquiadam\AcquiadamAuthService
   */
  protected $acquidamAuth;

  /**
   * Validates end point.
   */
  public function testGetEndpoint() {
    $endPoint = $this->acquidamAuth::getEndpoint('oauth/logout');

    $this->assertStringContainsString('subdomain.widencollective.com', $endPoint);
    $this->assertStringContainsString('/api/rest/oauth/logout', $endPoint);
  }

  /**
   * Validates the auth link that gets created.
   */
  public function testGetAuthLink() {
    $authLink = $this->acquidamAuth::generateAuthUrl('localhost.com/user/acquiadam/auth?uid=1');

    $this->assertStringContainsString('subdomain.widencollective.com', $authLink);
    $this->assertStringContainsString('/allowaccess', $authLink);
    $this->assertStringContainsString('?client_id=' . $this->acquidamAuth::CLIENT_ID, $authLink);
    $this->assertStringContainsString('&redirect_uri=localhost.com/user/acquiadam/auth?uid=1', $authLink);
  }

  /**
   * Validates we are getting access token and username from authentication.
   */
  public function testAuthenticate() {
    $authResponse = $this->acquidamAuth::authenticate('n00y84cn9gto9989hrh89e3606c89yui');

    $this->assertObjectHasAttribute('access_token', $authResponse);
    $this->assertObjectHasAttribute('username', $authResponse);
    $this->assertNotEmpty($authResponse->access_token);
    $this->assertStringContainsString('subdomain/l0p94ab7m05646d0a7f2dc023b94nm90', $authResponse->access_token);
    $this->assertStringContainsString('abc@abc.com', $authResponse->username);
  }

  /**
   * Validates we can cencel dam authentication.
   */
  public function testCancel() {
    $cancelResponse = $this->acquidamAuth::cancel('subdomain/l0p94ab7m05646d0a7f2dc023b94nm90');

    $this->assertEquals(1, $cancelResponse);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $acquidamAuth = new AcquiadamAuthService();
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn('{"access_token":"subdomain/l0p94ab7m05646d0a7f2dc023b94nm90", "username":"abc@abc.com"}');

    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn('200');

    $http_client = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->setMethods(['post'])
      ->getMock();
    $http_client->expects($this->any())->method('post')->willReturn($response);

    $messenger = $this->getMockBuilder(Messenger::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    \Drupal::setContainer($this->container);
    $this->container->set('http_client', $http_client);
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('messenger', $messenger);
    $this->container->set('media_acquiadam.auth', $acquidamAuth);
    $this->acquidamAuth = \Drupal::service('media_acquiadam.auth');
  }

}
