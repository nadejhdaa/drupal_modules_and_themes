<?php

declare(strict_types=1);

namespace Drupal\site_migrate_content\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a term_generate plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: term_generate
 *     source: foo
 * @endcode
 */
#[MigrateProcess('term_generate')]
final class TermGenerate extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {

    if (!isset($this->configuration['destination_bundle'])) {
      throw new MigrateException('Destination bundle must be set.');
    }

    $vid = $this->configuration['destination_bundle'];
    $delimiter = $this->configuration['delimiter'];

    $value = trim($value ?? '');
    $result = [];

    if (!empty($value)) {
      $items = explode(';', $value);

      foreach ($items as $item) {
        // Prepare TERM.
        $query = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->getQuery();
        $query->condition('status', 1)
          ->condition('name', $value)
          ->condition('vid', $vid)
          ->accessCheck(FALSE);
        $tids = $query->execute();
        $tid = reset($tids) ?: NULL;

        if ($tid === NULL) {
          $term = Term::create([
            'vid' => $vid,
            'status' => '1',
            'name' => $value,
          ]);
          $term->save();
          $result[] = ['target_id' => $term->id()];
        }
        else {
          $result[] = ['target_id' => $tid];
        }
      }

    }
    return $result;
  }

}
