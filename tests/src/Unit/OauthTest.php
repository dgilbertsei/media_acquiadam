<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\media_acquiadam\Oauth;
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

  /**
   * Validates the auth link that gets created.
   */
  public function testGetAuthLink() {
    $oauth = Oauth::create(Drupal::getContainer(), [], '', []);
    $authUrl = $oauth->getAuthLink();

    $this->assertContains('some/url/test', $authUrl);
    $this->assertContains('testToken112233', $authUrl);
    $this->assertContains('WDclient-id', $authUrl);
    $this->assertContains('/oauth2/authorize', $authUrl);
  }

  /**
   * Validates that the redirect URL gets generated correctly.
   */
  public function testGetSetAuthFinishRedirect() {
    $oauth = Oauth::create(Drupal::getContainer(), [], '', []);
    $redirect = $oauth->getAuthFinishRedirect();
    $this->assertNull($redirect);

    $oauth->setAuthFinishRedirect('https://example.com/sub/path?original_path=should-be-dropped&extra=1');
    $redirect = $oauth->getAuthFinishRedirect();
    $this->assertSame('https://example.com/sub/path?extra=1', $redirect);
  }

  /**
   * Validates that the access token response has the necessary keys.
   */
  public function testGetAccessToken() {

    $oauth = Oauth::create(Drupal::getContainer(), [], '', []);
    $token = $oauth->getAccessToken('somedummycode123');

    $this->assertArrayHasKey('expire_time', $token);
    $this->assertArrayHasKey('access_token', $token);
    $this->assertNotEmpty($token['access_token']);
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
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $csrf_token = $this->getMockBuilder(CsrfTokenGenerator::class)
      ->disableOriginalConstructor()
      ->getMock();
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

    $url_generator = $this->getMockBuilder(UrlGeneratorInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->any())
      ->method('generateFromRoute')
      ->willReturn('some/url/test');

    $unrouted_url_assembler = $this->getMockBuilder(UnroutedUrlAssemblerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    // @BUG: Forcing the UnroutedUrlAssembler return here forces a pass.
    // UnroutedUrlAssembler is called by toString() in setAuthFinishRedirect
    // and is overly complicated to mock/replace.
    $unrouted_url_assembler->expects($this->any())
      ->method('assemble')
      ->willReturn('https://example.com/sub/path?extra=1');

    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn('{"access_token":"ACCESS_TOKEN", "token_type":"bearer", "expires_in":3600, "refresh_token": "refresh_token"}');

    $http_client = $this->getMockBuilder(GuzzleClient::class)
      ->disableOriginalConstructor()
      ->setMethods(['post'])
      ->getMock();
    $http_client->expects($this->any())->method('post')->willReturn($response);

    $current_user = $this->getMockBuilder(AccountProxyInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger_channel = $this->getMockBuilder(LoggerChannelInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger_factory = $this->getMockBuilder(LoggerChannelFactoryInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger_factory->expects($this->any())
      ->method('get')
      ->with('media_acquiadam')
      ->willReturn($logger_channel);

    $container = new ContainerBuilder();

    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('config.factory', $this->getConfigFactoryStub());
    $container->set('csrf_token', $csrf_token);
    $container->set('unrouted_url_assembler', $unrouted_url_assembler);
    $container->set('url_generator.non_bubbling', $url_generator);
    $container->set('http_client', $http_client);
    $container->set('logger.factory', $logger_factory);
    $container->set('current_user', $current_user);

    Drupal::setContainer($container);
  }

}
