<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\Null\Query;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\State;
use Drupal\media_acquiadam\Acquiadam;
use Drupal\media_acquiadam\Exception\InvalidCredentialsException;
use Drupal\media_acquiadam\Service\AssetRefreshManager;
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

  use AcquiadamLoggerFactoryTrait;

  protected const LAST_EDITED_TIME = "lastEditDate:[after 1970-01-01T10:00:00Z]";

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
          'query' => self::LAST_EDITED_TIME,
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
          'query' => self::LAST_EDITED_TIME,
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
          'query' => self::LAST_EDITED_TIME,
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
      ->expects($this->any())
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
      ->setMethods(['searchAssets'])
      ->disableOriginalConstructor()
      ->getMock();

    $this->state = $this->getMockBuilder(State::class)
      ->disableOriginalConstructor()
      ->getMock();

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
      ->setMethods(['orConditionGroup', 'condition', 'execute', 'get'])
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

    $this->container = new ContainerBuilder();
    $this->container->set('state', $this->state);
    $this->container->set('logger.factory', $this->getLoggerFactoryStub());
    $this->container->set('queue', $queue_factory);
    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('media_acquiadam.acquiadam', $this->acquiadamClient);
    \Drupal::setContainer($this->container);

    $this->assetRefreshManager = AssetRefreshManager::create($this->container);
    $this->assetRefreshManager->setRequestLimit(3);
  }

}
