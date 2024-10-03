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
use Drupal\site_migrate_content\Utility\Utility;
use Drupal\site_content\EncMedia;
use Drupal\migrate\MigrateException;

/**
 * Provides a body_with_imgs plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: body_with_imgs
 *     source: foo
 * @endcode
 */
#[MigrateProcess('body_with_imgs')]
final class BodyWithImgs extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('entity_type.manager'),
      $container->get('site_media'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    if (!empty($value)) {
      $body_imgs = $row->getSourceProperty('body_imgs');
      $title = $row->getSourceProperty('title');

      $media_uuids = [];
      if (!empty($body_imgs)) {
        $img_data = [];

        $body_img_rows = explode(';', $body_imgs);

        foreach ($body_img_rows as $body_img_row) {
          $img_prop_arr = explode(',,,', $body_img_row);

          foreach ($img_prop_arr as $img_prop) {
            $img_prop_arr = explode('=', $img_prop);
            $img_data[$img_prop_arr[0]] = $img_prop_arr[1];
          }

          $data = [
            'fid' => $img_data['fid'],
            'file_path' => $img_data['path'],
            'file_uri' => $img_data['uri'],
            'alt' => $title,
          ];

          $media = $this->encMedia->createMediaFromRemote($data);
          if (!empty($media)) {
            $media_uuids[] = $media->uuid();
          }
        }
      }

      // Fetch attributes.
      $doc = new \DOMDocument();
      $value_without_style = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $value );
      @$doc->loadHTML($value_without_style);
      $imgs = $doc->getElementsByTagName('img');

      $all_attributes = [];
      if (!empty($imgs)) {
        foreach ($imgs as $key => $img) {
          $attr_names = $img->getAttributeNames();

          foreach ($attr_names as $attr_name) {
            $all_attributes[$key][$attr_name] = $img->getAttribute($attr_name);
          }
        }
      }

      // Replace images with medis.
      preg_match_all('/<img[^>]+>/i', $value_without_style, $images);

      foreach ($images[0] as $key => $image) {
        $uuid = $media_uuids[$key];

        $media_attributes_str = '';

        if (!empty($all_attributes[$key])) {
          foreach ($all_attributes[$key] as $attr_name => $attr_value) {
            $no_media_attributes = $this->encMedia->getNoMediAttributes();
            if (!in_array($attr_name, $no_media_attributes)) {
              $media_attributes[] = $attr_name .'="' . $attr_value . '"';
            }
          }

          $media_attributes_str = !empty($media_attributes) ? implode(' ', $media_attributes) : '';
        }

        $media_tag = '<drupal-media data-entity-type="media" data-entity-uuid="' . $uuid . '" ' . $media_attributes_str . '>&nbsp;</drupal-media>';

        $value = str_replace($image, $media_tag, $value);
      }
    }

    return $value;
  }

}
