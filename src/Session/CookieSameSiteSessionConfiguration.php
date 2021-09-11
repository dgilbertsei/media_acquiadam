<?php

namespace Drupal\acquiadam\Session;

use Drupal\Core\Session\SessionConfiguration;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the default session configuration generator.
 */
class CookieSameSiteSessionConfiguration extends SessionConfiguration {

  /**
   * {@inheritdoc}
   */
  public function getOptions(Request $request) {
    $options = parent::getOptions($request);

    // Return defaults if configuration tells us to disable the bypass.
    $config = \Drupal::configFactory()->getEditable('acquiadam.settings');
    if ($config->get('samesite_cookie_disable')) {
      return $options;
    }

    // Set the cookie samesite option to None.
    if (isset($options['cookie_secure']) && $options['cookie_secure'] == TRUE) {
      $options['cookie_samesite'] = 'None';
    }

    return $options;
  }

}
