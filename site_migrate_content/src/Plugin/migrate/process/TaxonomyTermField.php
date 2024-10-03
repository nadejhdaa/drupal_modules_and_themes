<?php

declare(strict_types=1);

namespace Drupal\site_migrate_content\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provides a taxonomy_term_field plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: taxonomy_term_field
 *     source: foo
 * @endcode
 */
#[MigrateProcess('taxonomy_term_field')]
final class TaxonomyTermField extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    $name = $this->configuration['name'];

    $source = $row->getSource();
    $data = unserialize($source['data']);
    $data2 = unserialize($source['data2']);
    $data = array_merge($data, $data2);
    $type = $source['type'];

    switch ($name) {
      case 'type':
        $value = $type;

        if ($type == 'image') {
          $value = 'entity_reference';
        }
        elseif ($type == 'number_decimal') {
          $value = 'decimal';
        }
        break;
      case 'indexes':

        if ($type == 'image') {
          $value = [];
        }
        else {
          $value = $data['indexes'];
        }

        break;

      case 'settings':
        if ($type == 'image') {
          $value = [
            'handler' => 'default:media',
            'handler_settings' => [
              'target_bundles' => [
                'image' => 'image',
              ],
              'sort' => [
                'field' => '_none',
                'direction' => 'ASC',
              ],
              'auto_create' => false,
              'auto_create_bundle' => '',
            ]
          ];
        }
        else {
          if (!empty($data['settings'])) {
            $value = $data['settings'];
          }
        }

        break;
    }

    return $value;
  }

}
