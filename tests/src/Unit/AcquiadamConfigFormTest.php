<?php

namespace Drupal\Tests\media_acquiadam\Unit;

use Drupal;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\media_acquiadam\Client;
use Drupal\media_acquiadam\ClientFactory;
use Drupal\media_acquiadam\Form\AcquiadamConfig;
use Drupal\Tests\media_acquiadam\Traits\AcquiadamConfigTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Config form test.
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
   * Media: Acquia DAM config form.
   *
   * @var \Drupal\media_acquiadam\Form\AcquiadamConfig
   */
  protected $acquiaDamConfig;

  /**
   * {@inheritdoc}
   */
  public function testGetFormId() {
    $this->assertEquals('acquiadam_config',
      $this->acquiaDamConfig->getFormId());
  }

  /**
   * {@inheritdoc}
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

    $this->assertArrayHasKey('image', $form);
    $this->assertEquals(1280, $form['image']['size_limit']['#default_value']);
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

    $this->container = new ContainerBuilder();
    $this->container->set('string_translation',
      $this->getStringTranslationStub());
    $this->container->set('media_acquiadam.client_factory',
      $acquiadam_client_factory);
    $this->container->set('config.factory', $this->getConfigFactoryStub());
    Drupal::setContainer($this->container);

    $this->acquiaDamConfig = AcquiadamConfig::create($this->container);
  }

}
