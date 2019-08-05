<?php

namespace Drupal\Tests\media_acquiadam\Traits;

/**
 * A shared mock config factory service.
 *
 * Provides configuration used by the different tests.
 */
trait AcquiadamConfigTrait {

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
      'system.file' => ['default_scheme' => 'public'],
      'media.settings' => ['icon_base_uri' => 'public://media-icons'],
    ] + $configs);
  }

}
