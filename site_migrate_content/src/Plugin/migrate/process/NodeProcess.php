<?php

declare(strict_types=1);

namespace Drupal\site_migrate_content\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;
use \Drupal\node\Entity\Node;

/**
 * Provides a node_process plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: node_process
 *     source: foo
 * @endcode
 */
#[MigrateProcess('node_process')]
final class NodeProcess extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  const DOMAINS = [
    'ГНЦ РФ ФГБУ «НМИЦ эндокринологии» Минздрава России' => 'endocrincentr_ru',
    'Личный кабинет' => 'lk_endocrincentr_ru',
  ];

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RedirectRepository $redirectRepository,
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
      $container->get('redirect.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    switch ($destination_property) {
      case 'field_news_date':
        if (!empty($value)) {
          $value = substr($value, 0, 10);
        }
        else {
          $created = $row->getSourceProperty('created');
          $value = date('Y-m-d', intval($created));
        }
        break;
      case 'redirect':
        if (!empty($value)) {
          $nid = $row->getSourceProperty('nid');

          $path = ltrim($value, $value[0]);
          $exists = $this->redirectRepository->findBySourcePath($path);
          if (empty($exists)) {
            Redirect::create([
              'redirect_source' => ['path' => $value],
              'redirect_redirect' => "entity:node/{$nid}",
              'language' => 'ru',
              'status_code' => '301',
            ])->save();
          }
        }

        break;

      case 'field_hide_on_list':
        $field_newsspec_cover = $row->getSourceProperty('field_newsspec_cover');
        $type = $row->getSourceProperty('type');
        $field_newsspec_section = $row->getSourceProperty('field_newsspec_section');

        $value = '0';
        if (empty($field_newsspec_cover)) {
          $value = '1';
        }
        if ($type == 'news_specialists' && $field_newsspec_section == 'Объявления о защитах диссертаций') {
          $value = '1';
        }
        break;

      case 'field_newsspec_date':
        if (!empty($value)) {
          $value = substr($value, 0, 10);
        }
        else {
          $created = $row->getSourceProperty('created');
          $value = date('Y-m-d', $created);
        }
        break;

      case 'field_migrate_comment':
        if (!empty($value)) {
          $attention_formats = [
            'php_code',
            'noindex_exlinks',
            'token',
            'html_mail',
            'image_resize_nolink',
          ];
          if (in_array($value, $attention_formats)) {
            $msg[] = 'body format = "' . $value . '"';
          }
          else {
            $msg[] = '';
          }

          $node_type = $row->getSourceProperty('type');

          if ($node_type == 'news' || $node_type == 'news_specialists') {

            // Проверить body inline images.
            $msg = [];
            $body_imgs = $row->getSourceProperty('body_imgs');

            if (!empty($body_imgs)) {
              $img_data = [];

              $body_img_rows = explode(';', $body_imgs);

              foreach ($body_img_rows as $body_img_row) {
                $img_prop_arr = explode(',,,', $body_img_row);

                foreach ($img_prop_arr as $img_prop) {
                  $img_prop_arr = explode('=', $img_prop);

                  if ($img_prop_arr[0] == 'path' && !empty($img_prop_arr[1])) {
                    $path = urldecode($img_prop_arr[1]);

                    $dir_path = \Drupal::service('file_system')->realpath('public://old_files/');
                    $path = $dir_path . urldecode($path);

                    $file_content = file_get_contents($path);
                    if (empty($file_content)) {
                      $msg[] = 'Файл "' . $path . '" не удалось скачать.';
                    }
                  }
                }
              }
            }

            // Проверить imagefields.
            $img_fields = ['field_newsspec_cover', 'field_image'];

            foreach ($img_fields as $img_field) {
              $path = $row->getSourceProperty($img_field);
              if (!empty($path)) {
                $image_content = file_get_contents(urldecode($path));
                if (empty($image_content)) {
                  $msg[] = 'Файл "' . $path . '" не удалось скачать';
                }
              }
            }

            if (!empty($msg)) {
              $value = implode('.
', $msg);
            }
          }
        }

        break;

        case 'field_meta_tags':
          if (!empty($value)) {
            $metatags_value_arr = [];
            $metatags = explode(';', $value);
            foreach ($metatags as $metatag) {
              $metatag_prop = explode('=', $metatag);
              $metatags_value_arr[$metatag_prop[0]] = $metatag_prop[1];
            }
            $value = json_encode($metatags_value_arr);
          }
          break;

        case 'field_domain_all_affiliates':
          if (!empty($value)) {
            $value = '1';
          }
          else {
            $value = '0';
          }
          break;

        case 'field_domain_access':
          if (!empty($value)) {
            $domains = self::DOMAINS;
            $items = explode(',,,', $value);

            foreach ($items as $item) {
              if (!empty($domains[$item])) {
                $domains_ids[] = ['target_id' => $domains[$item]];
              }
            }
            $value = $domains_ids;
          }
          else {
            $value = [];
          }
          break;
    }
    return $value;
  }

}
