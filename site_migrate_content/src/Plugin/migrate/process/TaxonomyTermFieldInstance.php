<?php

declare(strict_types=1);

namespace Drupal\site_migrate_content\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Provides a taxonomy_term_field_instance plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: taxonomy_term_field_instance
 *     source: foo
 * @endcode
 */
#[MigrateProcess('taxonomy_term_field_instance')]
final class TaxonomyTermFieldInstance extends ProcessPluginBase {

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
      case 'settings':
        $settings = [];

        if ($type == 'image') {
          $settings['target_type'] = 'media';
        }

        else {
          if (!empty($data['settings'])) {
            $settings = $data['settings'];
          }

          if (!empty($data2['settings'])) {
            foreach ($data2['settings'] as $key => $value) {
              if (empty($settings[$key])) {
                $settings[$key] = $value;
              }
            }
          }
        }

        $value = $settings;
        break;

      case 'label':
        $value = $data['label'];
        break;

      case 'description':
        $value = $data['description'];
        break;

      case 'required':
        $value = empty($data['required']) ? false : true;
        break;
    }

    return $value;
  }

}
