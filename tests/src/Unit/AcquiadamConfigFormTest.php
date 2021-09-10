<?php

namespace Drupal\Tests\acquiadam\Unit;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\State\State;
use Drupal\acquiadam\Client;
use Drupal\acquiadam\ClientFactory;
use Drupal\acquiadam\Form\AcquiadamConfig;
use Drupal\acquiadam\Plugin\QueueWorker\AssetRefresh;
use Drupal\Tests\acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Config form test.
 *
 * @coversDefaultClass \Drupal\acquiadam\Form\AcquiadamConfig
 *
 * @group acquiadam
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
   * Media: Acquia DAM config form.
   *
   * @var \Drupal\Tests\acquiadam\Unit\AcquiadamConfig
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
   * @var \Drupal\acquiadam\Plugin\QueueWorker\AssetRefresh|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $queueWorker;

  /**
   * {@inheritdoc}
   *
   * @covers ::getFormId
   */
  public function testGetFormId() {
    $this->assertEquals('acquiadam_config',
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
    $this->assertArrayHasKey('username', $form['authentication']);
    $this->assertArrayHasKey('password', $form['authentication']);
    $this->assertArrayHasKey('client_id', $form['authentication']);
    $this->assertArrayHasKey('secret', $form['authentication']);

    $this->assertEquals('WDusername',
      $form['authentication']['username']['#default_value']);
    $this->assertEquals('WDpassword',
      $form['authentication']['password']['#default_value']);
    $this->assertEquals('WDclient-id',
      $form['authentication']['client_id']['#default_value']);
    $this->assertEquals('WDsecret',
      $form['authentication']['secret']['#default_value']);

    $this->assertArrayHasKey('cron', $form);
    $this->assertEquals('14400',
      $form['cron']['sync_interval']['#default_value']);
    $this->assertEquals(1, $form['cron']['notifications_sync']['#default_value']);

    $this->assertArrayHasKey('image', $form);
    $this->assertEquals(1280, $form['image']['size_limit']['#default_value']);

    $this->assertArrayHasKey('manual_sync', $form);
    $this->assertArrayHasKey('perform_manual_sync', $form['manual_sync']);
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

    // Verify the batch finish operation.
    $this->state->expects($this->exactly(3))
      ->method('set')
      ->withConsecutive(
        [$this->equalTo('acquiadam.notifications_starttime'), $this->equalTo(1560000000)],
        [$this->equalTo('acquiadam.notifications_endtime'), $this->equalTo(NULL)],
        [$this->equalTo('acquiadam.notifications_next_page'), $this->equalTo(NULL)]
      );
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
  protected function setUp() {
    parent::setUp();

    // We need to override the DAM client so that we can fake authentication.
    $dam_client = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->getMock();

    // We do not actually care about validating anything at this point, but
    // the validateForm method does a basic "does authentication work" check.
    $dam_client->expects($this->any())
      ->method('getAccountSubscriptionDetails')
      ->willReturn([]);

    // We need to make sure we get our mocked class instead of the original.
    $acquiadam_client_factory = $this->getMockBuilder(ClientFactory::class)
      ->disableOriginalConstructor()
      ->getMock();
    $acquiadam_client_factory->expects($this->any())
      ->method('getWithCredentials')
      ->willReturn($dam_client);

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
    $this->container->set('acquiadam.client_factory',
      $acquiadam_client_factory);
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
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\acquiadam\Form\AcquiadamConfig
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
        $this->container->get('acquiadam.client_factory'),
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
