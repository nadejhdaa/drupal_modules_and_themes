<?php

declare(strict_types=1);

namespace Drupal\site_migrate_content\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateException;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\file\Entity\File;
use Drupal\site_content\EncMedia;

/**
 * Provides a media_generate1 plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: media_generate
 *     source: foo
 * @endcode
 */
#[MigrateProcess('media_generate')]
final class MediaGenerate extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly DatabaseFileUsageBackend $fileUsage,
    private readonly EncMedia $encMedia,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('site_media'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    if (!isset($this->configuration['destination_field'])) {
      throw new MigrateException('Destination field must be set.');
    }
    if (!isset($this->configuration['destination_bundle'])) {
      throw new MigrateException('Destination bundle must be set.');
    }

    $source_field_name = $this->configuration['source_field_name'];

    if (!empty($value)) {
      $file_uri = $row->getSourceProperty("{$source_field_name}_uri");
      $fid = $row->getSourceProperty("{$source_field_name}_fid");
      $alt = $row->getSourceProperty('title');
      $file_path = $value;

      $data = [
        'file_uri' => $file_uri,
        'fid' => $fid,
        'alt' => $alt,
        'file_path' => $file_path,
      ];

      $media = $this->encMedia->createMediaFromRemote($data);
      if (!empty($media)) {
        return $media->id();
      }

    }
    return NULL;
  }


}
