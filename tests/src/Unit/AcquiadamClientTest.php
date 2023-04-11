<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\media_acquiadam\Client;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Client factory test.
 *
 * @group media_acquiadam
 * @coversDefaultClass \Drupal\media_acquiadam\Client
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
   * @var \Drupal\media_acquiadam\Client
   */
  protected $client;

  /**
   * Checks if the service is created in the Drupal context.
   */
  public function testClient() {
    $this->assertNotNull(\Drupal::service('media_acquiadam.client'));
  }

  /**
   * Check if user is authenticated.
   */
  public function testCheckAuth() {
    $this->assertTrue($this->client->checkAuth());
  }

  /**
   * @covers ::getAssetsByCategory
   * @testWith [true, "category:({FooBarBaz})"]
   *           [false, "category:FooBarBaz"]
   */
  public function testGetAssetsByCategory(?bool $use_exact_search, string $expected_category_param): void {
    $client = new HttpClient([
      'handler' => HandlerStack::create(
        static function (RequestInterface $request) use ($expected_category_param): PromiseInterface {
          $params = [];
          parse_str($request->getUri()->getQuery(), $params);
          $query_parts = explode(' ', $params['query'] ?? '');
          self::assertEquals($expected_category_param, $query_parts[0]);
          return new FulfilledPromise(new Response(200, [], json_encode([
            'total_count' => 0,
            'items' => [],
          ])));
        }
      ),
    ]);

    $sut = new Client(
      $client,
      $this->createMock(UserDataInterface::class),
      new AnonymousUserSession(),
      $this->getConfigFactoryStub([
        'media_acquiadam.settings' => [
          'token' => 'demo/121someRandom1342test32st',
          'sync_interval' => 3600,
          'sync_method' => "updated_date",
          'transcode' => 'transcode',
          'sync_perform_delete' => 1,
          'size_limit' => 1280,
          'report_asset_usage' => 1,
          'domain' => 'subdomain.widencollective.com',
          'client_id' => 'a3mf039fd77dw67886459q90098z0980.app.widen.com',
          'exact_category_search' => $use_exact_search,
        ],
      ]),
      new RequestStack()
    );
    $sut->getAssetsByCategory('FooBarBaz', [], $use_exact_search);
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

    $request_stack = $this->getMockBuilder(RequestStack::class)
      ->disableOriginalConstructor()
      ->getMock();

    $client = new Client($http_client, $user_data, $current_user, $this->getDefaultConfigFactoryStub(), $request_stack);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
    $container->set('media_acquiadam.client', $client);
    $this->client = \Drupal::service('media_acquiadam.client');
  }

}
