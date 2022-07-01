<?php

namespace Drupal\media_acquiadam\Plugin\Linkit\Substitution;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\file\FileInterface;
use Drupal\linkit\Plugin\Linkit\Substitution\Media;
use Drupal\media\MediaInterface;
use Drupal\media_acquiadam\Plugin\media\Source\AcquiadamAsset;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A substitution plugin for the URL to a file.
 *
 * Custom plugin for DAM assets because the source field is the DAM ID, not the
 * actual file reference field.
 *
 * @Substitution(
 *   id = "dam_asset",
 *   label = @Translation("Direct URL to DAM file entity"),
 * )
 */
class DAMAsset extends Media {

  /**
   * Drupal entity type management service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
    if ($container->has('file_url_generator')) {
      $instance->fileUrlGenerator = $container->get('file_url_generator');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity) {

    // We need special handling for Acquia DAM media sources.
    // LinkIt assumes that a Media source field will be a FileInterface which is
    // not a valid assumption.
    if ($entity instanceof MediaInterface) {
      $source = $entity->getSource();
      if (!empty($source) && $source instanceof AcquiadamAsset) {
        $fid = $source->getMetadata($entity, 'file');
        if (!empty($fid)) {
          $file = $this->entityTypeManager->getStorage('file')->load($fid);
          // This is the original LinkIt behavior.
          if (!empty($file) && $file instanceof FileInterface) {
            $url = new GeneratedUrl();
            if ($this->fileUrlGenerator instanceof FileUrlGeneratorInterface) {
              $generated_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
            }
            else {
              // @phpstan-ignore-next-line
              $generated_url = file_create_url($file->getFileUri());
            }
            $url->setGeneratedUrl($generated_url);
            $url->addCacheableDependency($entity);
            return $url;
          }
        }
      }
    }

    return parent::getUrl($entity);
  }

}
