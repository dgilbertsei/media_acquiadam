<?php

namespace Drupal\media_acquiadam;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Acquiadam.
 *
 * Abstracts away details of the REST API.
 *
 * @package Drupal\media_acquiadam
 */
class Acquiadam implements AcquiadamInterface, ContainerFactoryPluginInterface {

  /**
   * A dam client.
   *
   * @var \cweagans\webdam\Client
   */
  protected $client;

  /**
   * Acquiadam constructor.
   *
   * @param \Drupal\media_acquiadam\ClientFactory $client_factory
   *   An instance of ClientFactory that we can get a webdam client from.
   * @param string $credential_type
   *   The type of credentials to use.
   */
  public function __construct(ClientFactory $client_factory, $credential_type) {
    $this->client = $client_factory->get($credential_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('media_acquiadam.client_factory'),
      'background'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFlattenedFolderList($folder_id = NULL) {
    $folder_data = [];

    if (is_null($folder_id)) {
      $folders = $this->client->getTopLevelFolders();
    }
    else {
      $folder = $this->client->getFolder($folder_id);
      $folders = !empty($folder->folders) ? $folder->folders : [];
    }

    foreach ($folders as $folder) {
      $folder_data[$folder->id] = $folder->name;

      $folder_list = $this->getFlattenedFolderList($folder->id);

      foreach ($folder_list as $folder_id => $folder_name) {
        $folder_data[$folder_id] = $folder_name;
      }
    }

    return $folder_data;
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, $arguments) {
    $method_variable = [$this->client, $name];
    if (is_callable($method_variable)) {
      return call_user_func_array($method_variable, $arguments);
    }
  }

}
