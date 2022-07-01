<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\Null\Query;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\State;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\Exception\InvalidCredentialsException;
use Drupal\media_acquiadam\Service\AssetRefreshManager;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamLoggerFactoryTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\GuzzleException;

/**
 * AssetRefreshManager Service test.
 *
 * @coversDefaultClass \Drupal\media_acquiadam\Service\AssetRefreshManager
 *
 * @group media_acquiadam
 */
class AssetRefreshManagerTest extends UnitTestCase {

  use AcquiadamLoggerFactoryTrait, AcquiadamConfigTrait;

  protected const LAST_EDITED_DATE_QUERY = '(lastEditDate:[after 2022-05-24T19:14:42Z]) AND (lastEditDate:[before 2022-05-25T00:17:00Z])';

  protected const REQUEST_TIME = 1560000000;

  /**
   * DI container.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * AssetRefreshManager service.
   *
   * @var \Drupal\media_acquiadam\Service\AssetRefreshManagerInterface
   */
  protected $assetRefreshManager;

  /**
   * The Queue Worker.
   *
   * @var \Drupal\Core\Queue\QueueInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $queue;

  /**
   * The media entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityQuery;

  /**
   * The Acquiadam Service.
   *
   * @var \Drupal\media_acquiadam\AcquiadamInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $acquiadamClient;

  /**
   * The Drupal State Service.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $state;

  /**
   * Validate that we can modify the request limit property correctly.
   */
  public function testRequestLimitGetterSetter() {
    $expected = 3;

    $this->assertEquals($expected, $this->assetRefreshManager->getRequestLimit());
    $original = $this->assetRefreshManager->setRequestLimit($expected * 2);
    $this->assertEquals($expected, $original);
    $original = $this->assetRefreshManager->setRequestLimit($expected);
    $this->assertEquals($expected * 2, $original);
  }

  /**
   * Tests a "no asset id fields provided" scenario.
   */
  public function testEmptyAssetIdFields() {
    $actual = $this->assetRefreshManager->updateQueue([]);
    $this->assertEquals(0, $actual);
  }

  /**
   * Tests a "no matching media entity ids are found" scenario.
   *
   * @param array $request_query_options
   *   The list of request query options.
   * @param array $response
   *   The stub of Search API response.
   * @param array $expected_asset_ids
   *   The list of expected asset ids.
   *
   * @dataProvider providerTestInterruptedFetch
   */
  public function testNoMatchingMediaEntityIds(array $request_query_options, array $response, array $expected_asset_ids) {

    $this->setupApiResponseStub($request_query_options, $response);

    $this->entityQuery->expects($this->any())
      ->method('orConditionGroup')
      ->will($this->returnSelf());

    $this->entityQuery->expects($this->any())
      ->method('condition')
      ->withConsecutive(
        [$this->equalTo('bundle'), $this->equalTo('test_bundle')],
        [$this->equalTo('field_1'), $this->equalTo($expected_asset_ids)])
      ->will($this->returnSelf());
    $this->entityQuery->expects($this->any())
      ->method('execute')
      ->willReturn([]);
    $this->queue->expects($this->never())
      ->method($this->anything());

    $actual = $this->assetRefreshManager->updateQueue($this->getAssetIdFieldsStub());
    $this->assertEquals(0, $actual);
  }

  /**
   * Tests a "non-interrupted API fetch" scenario.
   *
   * @param array $request_query_options
   *   The list of request query options.
   * @param array $response
   *   The stub of Search API response.
   * @param array $expected_asset_ids
   *   The list of expected asset ids.
   * @param int $expected_total
   *   The expected number of media entities to add to the queue.
   *
   * @dataProvider providerTestNonInterruptedFetch
   */
  public function testNonInterruptedFetch(array $request_query_options, array $response, array $expected_asset_ids, int $expected_total) {
    $this->setupApiResponseStub($request_query_options, $response);
    $this->setupMediaEntityExpectations($expected_asset_ids, $expected_total);

    $actual = $this->assetRefreshManager->updateQueue($this->getAssetIdFieldsStub());
    $this->assertEquals($expected_total, $actual);
  }

  /**
   * Tests when the cut-off is before the last sync time.
   *
   * @param string $last_sync_offset
   *   The offset for \DateTime::modify().
   * @param bool $expect_results
   *   TRUE if results expected, FALSE if not.
   *
   * @dataProvider provideTimeOffsets
   */
  public function testCutoffBeforeLastSync(string $last_sync_offset, bool $expect_results) {
    $real_time = new \DateTime('now');
    $sync_time = new \DateTime('now');
    $sync_time->modify($last_sync_offset);
    $state = $this->getMockBuilder(State::class)
      ->disableOriginalConstructor()
      ->getMock();
    $state->method('get')
      ->with('media_acquiadam.last_sync')
      ->willReturn($sync_time->getTimestamp());
    $time = $this->getMockBuilder(TimeInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $time->method('getCurrentTime')
      ->willReturn($real_time->getTimestamp());
    $asset_before_time = $real_time->modify('-1 hour');

    $this->setupApiResponseStub([
      'limit' => 3,
      'offset' => 0,
      'query' => "(lastEditDate:[after {$sync_time->format('Y-m-d\TH:i:s\Z')}]) AND (lastEditDate:[before {$asset_before_time->format('Y-m-d\TH:i:s\Z')}])",
      'include_deleted' => 'true',
      'include_archived' => 'true',
    ], [
      'total_count' => 3,
      'assets' => [
        (object) ['id' => '3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
        (object) ['id' => '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
        (object) ['id' => '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
      ],
    ]);
    $this->setupMediaEntityExpectations(
      ['3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
      3
    );

    $asset_refresh_manager = new AssetRefreshManager(
      $this->container->get('media_acquiadam.acquiadam'),
      $state,
      $this->container->get('logger.factory'),
      $this->container->get('queue'),
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $time
    );
    $asset_refresh_manager->setRequestLimit(3);
    $actual = $asset_refresh_manager->updateQueue($this->getAssetIdFieldsStub());
    $this->assertEquals($expect_results ? 3 : 0, $actual);
  }

  /**
   * Provides time offsets for verifying cut-off preflight check.
   *
   * The offset is for the last sync time from the current test time.
   *
   * @return \Generator
   *   The test data sets.
   */
  public function provideTimeOffsets() {
    yield '-55 minutes' => ['-55 minutes', FALSE];
    yield '+2 minutes' => ['+2 minutes', FALSE];
    yield '-1 hour' => ['-1 hour', TRUE];
    yield '-90 minutes' => ['-90 minutes', TRUE];
  }

  /**
   * Tests that transcoding at 'original' does not delay sync.
   */
  public function testNoDelayOnOriginal() {
    $this->setupApiResponseStub([
      'limit' => 3,
      'offset' => 0,
      'query' => 'lastEditDate:[after 2022-05-24T19:14:42Z]',
      'include_deleted' => 'true',
      'include_archived' => 'true',
    ], [
      'total_count' => 3,
      'assets' => [
        (object) ['id' => '3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
        (object) ['id' => '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
        (object) ['id' => '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
      ],
    ]);
    $this->setupMediaEntityExpectations(
      ['3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
      3
    );
    $config_factory = $this->getConfigFactoryStub([
      'media_acquiadam.settings' => [
        'token' => 'demo/121someRandom1342test32st',
        'sync_interval' => 3600,
        'sync_method' => "updated_date",
        'transcode' => 'original',
        'sync_perform_delete' => 1,
        'size_limit' => 1280,
        'report_asset_usage' => 1,
        'domain' => 'subdomain.widencollective.com',
        'client_id' => 'a3mf039fd77dw67886459q90098z0980.app.widen.com',
      ],
      'system.file' => ['default_scheme' => 'public'],
      'media.settings' => ['icon_base_uri' => 'public://media-icons'],
    ]);
    $asset_refresh_manager = new AssetRefreshManager(
      $this->container->get('media_acquiadam.acquiadam'),
      $this->container->get('state'),
      $this->container->get('logger.factory'),
      $this->container->get('queue'),
      $this->container->get('entity_type.manager'),
      $config_factory,
      $this->container->get('datetime.time')
    );
    $asset_refresh_manager->setRequestLimit(3);
    $actual = $asset_refresh_manager->updateQueue($this->getAssetIdFieldsStub());
    $this->assertEquals(3, $actual);
  }

  /**
   * Tests an "interrupted API fetch (a result set exceeds the limit)" scenario.
   *
   * @param array $request_query_options
   *   The list of request query options.
   * @param array $response
   *   The stub of Search API response.
   * @param array $expected_asset_ids
   *   The list of expected asset ids.
   * @param int $expected_total
   *   The expected number of media entities to add to the queue.
   *
   * @dataProvider providerTestInterruptedFetch
   */
  public function testInterruptedFetch(array $request_query_options, array $response, array $expected_asset_ids, int $expected_total) {
    $this->setupApiResponseStub($request_query_options, $response);
    $this->setupMediaEntityExpectations($expected_asset_ids, $expected_total);

    $actual = $this->assetRefreshManager->updateQueue($this->getAssetIdFieldsStub());
    $this->assertEquals(3, $actual);
  }

  /**
   * Tests a "failed API request" scenario.
   *
   * @param \Throwable $exception_stub
   *   Exception object stub.
   *
   * @dataProvider providerTestFailedApiRequest
   */
  public function testFailedApiRequest(\Throwable $exception_stub) {
    $this->acquiadamClient
      ->method('searchAssets')
      ->will($this->throwException($exception_stub));

    $actual = $this->assetRefreshManager->updateQueue($this->getAssetIdFieldsStub());
    $this->assertEquals(0, $actual);
  }

  /**
   * Provides test data for not-interrupted fetches (normal) related tests.
   *
   * @return array
   *   Test data (request query options, response, expected asset ids).
   */
  public function providerTestNonInterruptedFetch() {
    // Asset Objects.
    $asset_obj1 = new \StdClass();
    $asset_obj1->id = '3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a';
    $asset_obj2 = new \StdClass();
    $asset_obj2->id = '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a';
    $asset_obj3 = new \StdClass();
    $asset_obj3->id = '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a';
    return [
      [
        [
          'limit' => 3,
          'offset' => 0,
          'query' => self::LAST_EDITED_DATE_QUERY,
          'include_deleted' => 'true',
          'include_archived' => 'true',
        ],
        [
          'total_count' => 3,
          'assets' => [
            $asset_obj1,
            $asset_obj2,
            $asset_obj3,
          ],
        ],
        ['3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
        3,
      ],
      // No results.
      [
        [
          'limit' => 3,
          'offset' => 0,
          'query' => self::LAST_EDITED_DATE_QUERY,
          'include_deleted' => 'true',
          'include_archived' => 'true',
        ],
        [
          'total_count' => 0,
          'assets' => [],
        ],
        [],
        0,
      ],
    ];
  }

  /**
   * Provides test data for interrupted fetches related tests.
   *
   * @return array
   *   Test data (request query options, response, expected asset ids).
   */
  public function providerTestInterruptedFetch() {
    // Asset Objects.
    $asset_obj1 = new \StdClass();
    $asset_obj1->id = '3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a';
    $asset_obj2 = new \StdClass();
    $asset_obj2->id = '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a';
    $asset_obj3 = new \StdClass();
    $asset_obj3->id = '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a';
    return [
      [
        [
          'limit' => 3,
          'offset' => 0,
          'query' => self::LAST_EDITED_DATE_QUERY,
          'include_deleted' => 'true',
          'include_archived' => 'true',
        ],
        [
          'total_count' => 3,
          'assets' => [
            $asset_obj1,
            $asset_obj2,
            $asset_obj3,
          ],
        ],
        ['3f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '4f9bf79b-4fee-49f1-a852-3fdb7ca60f2a', '5f9bf79b-4fee-49f1-a852-3fdb7ca60f2a'],
        3,
      ],
    ];
  }

  /**
   * Provides test data for testing failed API requests.
   *
   * @return \Throwable[]
   *   Test data (GuzzleException and InvalidCredentialsException stubs).
   */
  public function providerTestFailedApiRequest(): array {
    return [
      [
        new /* @noinspection PhpSuperClassIncompatibleWithInterfaceInspection */
        class() extends \Exception implements GuzzleException {},
      ],
      [new InvalidCredentialsException()],
    ];
  }

  /**
   * Returns asset id fields stub.
   *
   * @return array
   *   Asset id fields stub where key is the bundle and value is the field name.
   */
  protected function getAssetIdFieldsStub() {
    return ['test_bundle' => 'field_1'];
  }

  /**
   * Setups the API response stub.
   *
   * @param array $request_query_options
   *   The list of request query options.
   * @param array $response
   *   The stub of Search API response.
   */
  protected function setupApiResponseStub(array $request_query_options, array $response) {
    $this->acquiadamClient
      ->method('searchAssets')
      ->with($request_query_options)
      ->willReturn($response);
  }

  /**
   * Setups the media entity query execution expectations.
   *
   * @param array $expected_asset_ids
   *   The list of expected asset ids.
   * @param int $expected_total
   *   The expected number of media entities to add to the queue.
   */
  protected function setupMediaEntityExpectations(array $expected_asset_ids, int $expected_total) {
    $this->entityQuery->expects($this->any())
      ->method('orConditionGroup')
      ->will($this->returnSelf());

    $this->entityQuery->expects($this->any())
      ->method('condition')
      ->withConsecutive(
        [$this->equalTo('bundle'), $this->equalTo('test_bundle')],
        [$this->equalTo('field_1'), $this->equalTo($expected_asset_ids)]
      )
      ->will($this->returnSelf());

    if (!$expected_asset_ids) {
      return;
    }

    $this->entityQuery->expects($this->any())
      ->method('execute')
      ->willReturn(range(1, $expected_total));

    $unit_test = $this;
    $expected_queue_items = array_map(
      function ($value) use ($unit_test) {
        return [$unit_test->equalTo(['media_id' => $value])];
      },
      range(1, $expected_total)
    );

    $this->queue->expects($this->any())
      ->method('createItem')
      ->withConsecutive(
        ...$expected_queue_items
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->acquiadamClient = $this->getMockBuilder(Acquiadam::class)
      ->addMethods(['searchAssets'])
      ->disableOriginalConstructor()
      ->getMock();

    $this->state = $this->getMockBuilder(State::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->state->method('get')
      ->with('media_acquiadam.last_sync')
      // 2022-05-24T09:14:42Z UTC.
      ->willReturn('1653383682');

    $this->queue = $this->getMockBuilder(DatabaseQueue::class)
      ->disableOriginalConstructor()
      ->getMock();
    /** @var \Drupal\Core\Queue\QueueFactory|\PHPUnit\Framework\MockObject\MockObject $queue_factory */
    $queue_factory = $this->getMockBuilder(QueueFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $queue_factory->method('get')
      ->willReturn($this->queue);

    $this->entityQuery = $this->getMockBuilder(Query::class)
      ->onlyMethods(['orConditionGroup', 'condition', 'execute'])
      ->disableOriginalConstructor()
      ->getMock();

    $entity_storage = $this->getMockBuilder(EntityStorageInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_storage->method('getQuery')->willReturn($this->entityQuery);

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['media', $entity_storage],
    ]);

    $language_manager = $this->getMockBuilder(LanguageManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $language_manager->method('getCurrentLanguage')
      ->willReturn(new Language(Language::$defaultValues));

    $time = $this->getMockBuilder(TimeInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $time->method('getCurrentTime')
      // 2022-05-24T15:17:00Z UTC.
      ->willReturn('1653405420');

    $this->container = new ContainerBuilder();
    $this->container->set('config.factory', $this->getDefaultConfigFactoryStub());
    $this->container->set('state', $this->state);
    $this->container->set('logger.factory', $this->getLoggerFactoryStub());
    $this->container->set('queue', $queue_factory);
    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('media_acquiadam.acquiadam', $this->acquiadamClient);
    $this->container->set('language_manager', $language_manager);
    $this->container->set('datetime.time', $time);
    \Drupal::setContainer($this->container);

    $this->assetRefreshManager = AssetRefreshManager::create($this->container);
    $this->assetRefreshManager->setRequestLimit(3);
  }

}
