<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\State\State;
use Drupal\media_acquiadam\Form\AcquiadamConfig;
use Drupal\media_acquiadam\Plugin\QueueWorker\AssetRefresh;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;

/**
 * Config form test.
 *
 * @coversDefaultClass \Drupal\media_acquiadam\Form\AcquiadamConfig
 *
 * @group media_acquiadam
 */
class AcquiadamConfigFormTest extends UnitTestCase {

  use AcquiadamConfigTrait;

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * Acquia DAM config form.
   *
   * @var \Drupal\Tests\media_acquiadam\Unit\AcquiadamConfig
   */
  protected $acquiaDamConfig;

  /**
   * Drupal State service.
   *
   * @var \Drupal\Core\State\State|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $state;

  /**
   * Queue Worker.
   *
   * @var \Drupal\media_acquiadam\Plugin\QueueWorker\AssetRefresh|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $queueWorker;

  /**
   * {@inheritdoc}
   *
   * @covers ::getFormId
   */
  public function testGetFormId() {
    $this->assertEquals('media_acquiadam_config',
      $this->acquiaDamConfig->getFormId());
  }

  /**
   * {@inheritdoc}
   *
   * @covers ::buildForm
   */
  public function testBuildForm() {
    $form = $this->acquiaDamConfig->buildForm([], new FormState());

    $this->assertArrayHasKey('authentication', $form);
    $this->assertArrayHasKey('token', $form['authentication']);
    $this->assertArrayHasKey('cron', $form);
    $this->assertArrayHasKey('sync_interval', $form['cron']);
    $this->assertArrayHasKey('sync_method', $form['cron']);
    $this->assertArrayHasKey('sync_perform_delete', $form['cron']);
    $this->assertArrayHasKey('image', $form);
    $this->assertArrayHasKey('size_limit', $form['image']);
    $this->assertArrayHasKey('manual_sync', $form);
    $this->assertArrayHasKey('perform_manual_sync', $form['manual_sync']);
    $this->assertArrayHasKey('misc', $form);
    $this->assertArrayHasKey('report_asset_usage', $form['misc']);

    $this->assertEquals("demo/121someRandom1342test32st",
      $form['authentication']['token']['#default_value']);
    $this->assertEquals(3600,
      $form['cron']['sync_interval']['#default_value']);
    $this->assertEquals("updated_date",
      $form['cron']['sync_method']['#default_value']);
    $this->assertEquals(1, $form['cron']['sync_perform_delete']['#default_value']);
    $this->assertEquals(1280, $form['image']['size_limit']['#default_value']);
    $this->assertEquals(1, $form['misc']['report_asset_usage']['#default_value']);
  }

  /**
   * Tests "Perform Manual Sync" button click.
   *
   * @covers ::performManualSync
   */
  public function testPerformManualSync() {
    $form = [];
    $form_state = $this->getMockBuilder(FormStateInterface::class)
      ->getMock();

    $this->assertFalse($this->acquiaDamConfig->performManualSync($form, $form_state));

    $this->acquiaDamConfig->method('getActiveMediaIds')->willReturn([0, 1, 2]);
    $this->assertEquals([0, 1, 2], $this->acquiaDamConfig->performManualSync($form, $form_state));
  }

  /**
   * Tests a batch processing.
   *
   * @covers ::processBatchItems
   * @covers ::finishBatchOperation
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testProcessBatchItems() {
    // Generate a set of test media entity ids.
    $media_ids = range(1, 12);
    $total_media_ids = count($media_ids);
    $context = [];

    $queue_worker_expected_arguments = array_map(function ($value) {
      return [['media_id' => $value]];
    }, $media_ids);
    $this->queueWorker->expects($this->any())
      ->method('processItem')
      ->withConsecutive(...$queue_worker_expected_arguments)
      ->willReturn(TRUE);

    // Emulate the three consecutive batch runs.
    foreach (range(1, 3) as $run) {
      // Perform a batch run and make necessary assertions.
      $this->acquiaDamConfig->processBatchItems($media_ids, $context);
      $this->assertBatchRun($context, $run, $total_media_ids);
    }

    $this->acquiaDamConfig->finishBatchOperation(NULL, $context['results'], []);
  }

  /**
   * Makes assertion during an emulated batch run.
   *
   * @param array $context
   *   The Batch context.
   * @param int $run
   *   The run index.
   * @param int $total_media_ids
   *   The total number of items added to the batch.
   */
  protected function assertBatchRun(array $context, int $run, int $total_media_ids) : void {
    $processed = AcquiadamConfig::BATCH_SIZE * $run;
    $processed = $processed > $total_media_ids ? $total_media_ids : $processed;

    $this->assertNotEmpty($context);
    $this->assertEquals($processed,
      $context['sandbox']['progress']);
    $this->assertEquals($total_media_ids,
      $context['sandbox']['max']);
    $this->assertEquals($total_media_ids,
      $context['results']['total']);
    if ($processed < $total_media_ids) {
      $this->assertEquals(range(1 + $processed, $total_media_ids),
        $context['sandbox']['items']);
    }
    else {
      $this->assertEmpty($context['sandbox']['items']);
    }
    $this->assertEquals(1560000000,
      $context['results']['start_time']);
    $this->assertEquals($processed,
      $context['results']['processed']);
    $this->assertEquals($processed / $total_media_ids,
      $context['finished']);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $http_client = $this->getMockBuilder(ClientInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $time = $this->getMockBuilder(Time::class)
      ->disableOriginalConstructor()
      ->getMock();
    $time->method('getRequestTime')
      ->willReturn(1560000000);

    $this->queueWorker = $this->getMockBuilder(AssetRefresh::class)
      ->disableOriginalConstructor()
      ->getMock();
    $queue_worker_manager = $this->getMockBuilder(QueueWorkerManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $queue_worker_manager->expects($this->any())
      ->method('createInstance')
      ->willReturn($this->queueWorker);

    $this->state = $this->getMockBuilder(State::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation',
      $this->getStringTranslationStub());
    $this->container->set('http_client', $http_client);
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    $this->container->set('datetime.time', $time);
    $this->container->set('plugin.manager.queue_worker', $queue_worker_manager);
    $this->container->set('state', $this->state);

    \Drupal::setContainer($this->container);

    $this->acquiaDamConfig = $this->getMockedAcquidamConfig();
  }

  /**
   * Get a partially mocked AcquiadamConfig object.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\media_acquiadam\Form\AcquiadamConfig
   *   A mocked version of the AcquiadamConfig form class.
   *
   * @throws \Exception
   */
  protected function getMockedAcquidamConfig() {

    $messenger = $this->getMockBuilder(Messenger::class)
      ->setMethods([
        'addWarning',
        'addStatus',
      ])
      ->disableOriginalConstructor()
      ->getMock();

    $config = $this->getMockBuilder(AcquiadamConfig::class)
      ->setConstructorArgs([
        $this->container->get('config.factory'),
        $this->container->get('http_client'),
        new BatchBuilder(),
        $this->container->get('datetime.time'),
        $this->container->get('plugin.manager.queue_worker'),
        $this->container->get('state'),
      ])
      ->setMethods([
        'batchSet',
        'getActiveMediaIds',
        'messenger',
      ])
      ->getMock();

    $config->method('messenger')->willReturn($messenger);

    return $config;
  }

}
