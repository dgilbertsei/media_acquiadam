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
        'token' => 'demo/121someRandom1342test32st',
        'sync_interval' => 3600,
        'sync_method' => "updated_date",
        'sync_perform_delete' => 1,
        'size_limit' => 1280,
        'report_asset_usage' => 1,
        'domain' => 'subdomain.widencollective.com',
        'client_id' => 'a3mf039fd77dw67886459q90098z0980.app.widen.com',
      ],
      'system.file' => ['default_scheme' => 'public'],
      'media.settings' => ['icon_base_uri' => 'public://media-icons'],
    ] + $configs);
  }

}
