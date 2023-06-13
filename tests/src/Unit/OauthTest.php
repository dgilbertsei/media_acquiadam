<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\media_acquiadam\Oauth;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamLoggerFactoryTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Oauth test.
 *
 * @group media_acquiadam
 */
class OauthTest extends UnitTestCase {

  use AcquiadamConfigTrait, AcquiadamLoggerFactoryTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Media: Acquia DAM oAuth client.
   *
   * @var \Drupal\media_acquiadam\Oauth
   */
  protected $oAuthClient;

  /**
   * Validates the auth link that gets created.
   */
  public function testGetAuthLink() {
    $authUrl = $this->oAuthClient->getAuthLink();

    $this->assertStringContainsString('some/url/test', $authUrl);
    $this->assertStringContainsString('testToken112233', $authUrl);
    $this->assertStringContainsString('WDclient-id', $authUrl);
    $this->assertStringContainsString('/oauth2/authorize', $authUrl);
  }

  /**
   * Validates that the redirect URL gets generated correctly.
   */
  public function testGetSetAuthFinishRedirect() {
    $this->assertNull($this->oAuthClient->getAuthFinishRedirect());

    $this->oAuthClient->setAuthFinishRedirect('https://example.com/sub/path?original_path=should-be-dropped&extra=1');
    $this->assertSame('https://example.com/sub/path?extra=1',
      $this->oAuthClient->getAuthFinishRedirect());
  }

  /**
   * Validates that the access token response has the necessary keys.
   */
  public function testGetAccessToken() {
    $token = $this->oAuthClient->getAccessToken('somedummycode123');

    $this->assertArrayHasKey('expire_time', $token);
    $this->assertArrayHasKey('access_token', $token);
    $this->assertNotEmpty($token['access_token']);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $csrf_token = $this->createMock(CsrfTokenGenerator::class);
    $csrf_token->expects($this->any())
      ->method('get')
      ->willReturn('testToken112233');
    $csrf_token->expects($this->any())
      ->method('validate')
      ->withAnyParameters()
      ->willReturn(FALSE);
    $csrf_token->expects($this->any())
      ->method('validate')
      ->with('testToken112233')
      ->willReturn(TRUE);

    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->expects($this->any())
      ->method('generateFromRoute')
      ->willReturn('some/url/test');

    $unrouted_url_assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    // @BUG: Forcing the UnroutedUrlAssembler return here forces a pass.
    // UnroutedUrlAssembler is called by toString() in setAuthFinishRedirect
    // and is overly complicated to mock/replace.
    $unrouted_url_assembler->expects($this->any())
      ->method('assemble')
      ->willReturn('https://example.com/sub/path?extra=1');

    $response = $this->createMock(ResponseInterface::class);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn('{"access_token":"ACCESS_TOKEN", "token_type":"bearer", "expires_in":3600, "refresh_token": "refresh_token"}');

    $http_client = $this->createMock(GuzzleClient::class);
    $http_client->expects($this->any())->method('post')->willReturn($response);

    $current_user = $this->createMock(AccountProxyInterface::class);

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation',
      $this->getStringTranslationStub());
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('csrf_token', $csrf_token);
    $this->container->set('unrouted_url_assembler', $unrouted_url_assembler);
    $this->container->set('url_generator.non_bubbling', $url_generator);
    $this->container->set('http_client', $http_client);
    $this->container->set('logger.factory', $this->getLoggerFactoryStub());
    $this->container->set('current_user', $current_user);
    \Drupal::setContainer($this->container);

    $this->oAuthClient = Oauth::create($this->container);
  }

}
