<?php

declare(strict_types=1);

namespace Drupal\auto_content\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\Sql\QueryFactory;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\taxonomy\Entity\Term;
// use Drupal\node\NodeInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
// use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Url;
use Drupal\Core\Batch\BatchBuilder;
// use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\auto_content\IntersectionService;
use Drupal\auto_content\MenuService;
use Drupal\auto_import\TermService;
use Drupal\auto_content\Utility\Utility;

/**
 * @todo Add class description.
 */
final class UpdateStructure implements ContainerInjectionInterface {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Constructs an UpdateStructure object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $connection,
    private readonly PhpTransliteration $transliteration,
    private readonly QueryFactory $entityQuerySql,
    private readonly MessengerInterface $messenger,
    private readonly IntersectionService $intersection,
    private readonly MenuService $menuService,
    private readonly TermService $termService,
  ) {}

  /**
    * {@inheritdoc}
    */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
    * {@inheritdoc}
    */
  public function runTermInsertUpdate($term) {
    $items = [];
    $vid = $term->bundle();
    if ($vid == Utility::VID_BRANDS) {
      if (empty($term->parent_id->target_id)) {
        $items[] = [
          $vid => [
            'term' => $term,
            'parent' => NULL,
          ],
          'vid' => $vid,
        ];

        // Add Services.
        $services_tree = $this->termService->getTree(Utility::VID_SERVICES);
        foreach ($services_tree as $service_item) {
          $items[] = [
            Utility::VID_BRANDS => [
              'term' => $term,
              'parent' => NULL,
            ],
            Utility::VID_SERVICES => [
              'term' => $service_item['term'],
              'parent' => NULL,
            ],
            'vid' => Utility::VID_SERVICES,
          ];

          foreach ($service_item['children'] as $service) {
            $items[] = [
              Utility::VID_BRANDS => [
                'term' => $term,
                'parent' => NULL,
              ],
              Utility::VID_SERVICES => [
                'term' => $service,
                'parent' => $service_item['term'],
              ],
              'vid' => Utility::VID_SERVICES,
            ];
          }
        }

        // Add Areas.
        $area_tree = $this->termService->getTree(Utility::VID_AREA);
        foreach ($area_tree as $area_item) {
          $items[] = [
            Utility::VID_BRANDS => [
              'term' => $term,
              'parent' => NULL,
            ],
            Utility::VID_AREA => [
              'term' => $area_item['term'],
              'parent' => NULL,
            ],
            'vid' => Utility::VID_AREA,
          ];
        }
      }
      else {

      }
    }

    $count = count($items);

    $module_path = \Drupal::service('extension.path.resolver')->getPath('module', 'auto_content');
    $batch_builder = (new BatchBuilder())->setTitle('Проверка терминов')
      ->setFinishCallback([$this, 'finished'])
      ->setFile($module_path . '/src/Update/UpdateStructure.php')
      ->setInitMessage('Запускается обновление. Кол-во терминов: ' . $count)
      ->setProgressMessage('Completed @current of @total.');

    $batch_builder->addOperation([$this, 'processItems'], [
      $items,
      $count,
    ]);

    batch_set($batch_builder->toArray());
  }

  /**
    * {@inheritdoc}
    */
  public function run($vids = []) {
    $items = [];
    foreach ($vids as $vid) {
      $tree = $this->termService->getTree($vid);

      // AUTO Vocabulary.
      if ($vid == Utility::VID_BRANDS) {
        foreach ($tree as $tid => $term) {
          if (!empty($term['term'])) {
            // Brand.
            $items[] = [
              $vid => [
                'term' => $term['term'],
                'parent' => NULL,
              ],
              'vid' => $vid,
            ];
          }
          if (!empty($term['children']) && $vid !== Utility::VID_AREA) {
            foreach ($term['children'] as $child) {
              // Brand + model.
              $items[] = [
                $vid => [
                  'term' => $child,
                  'parent' => $term['term'],
                ],
                'vid' => $vid,
              ];
            }
          }
        }
      }

      // SERVICES Vocabulary.
      if ($vid == Utility::VID_SERVICES) {
        $brands = $this->termService->getTree(Utility::VID_BRANDS);

        foreach ($brands as $brand) {
          if (!empty($brand['term'])) {

            foreach ($tree as $service) {
              // Brand + service parent.
              $items[] = [
                Utility::VID_BRANDS => [
                  'term' => $brand['term'],
                  'parent' => NULL,
                ],
                $vid => [
                  'term' => $service['term'],
                  'parent' => NULL,
                ],
                'vid' => $vid,
              ];

              if (!empty($service['children'])) {
                foreach ($service['children'] as $service_children) {
                  // Brand + service children.
                  $items[] = [
                    Utility::VID_BRANDS => [
                      'term' => $brand['term'],
                      'parent' => NULL,
                    ],
                    $vid => [
                      'term' => $service_children,
                      'parent' => $service['term'],
                    ],
                    'vid' => $vid,
                  ];
                }
              }
            }
          }
        }
      }

      // AREA Vocabulary.
      if ($vid == Utility::VID_AREA) {
        $brands = $this->termService->getTree(Utility::VID_BRANDS);

        foreach ($tree as $area) {
          foreach ($brands as $brand) {
            $items[] = [
              $vid => [
                'term' => $area['term'],
                'parent' => NULL,
              ],
              Utility::VID_BRANDS => [
                'term' => $brand['term'],
                'parent' => NULL,
              ],
              'vid' => $vid,
            ];
          }
        }
      }
    }

    $count = count($items);

    $module_path = \Drupal::service('extension.path.resolver')->getPath('module', 'auto_content');
    $batch_builder = (new BatchBuilder())->setTitle('Проверка терминов')
      ->setFinishCallback([$this, 'finished'])
      ->setFile($module_path . '/src/Update/UpdateStructure.php')
      ->setInitMessage('Запускается обновление. Кол-во терминов: ' . $count)
      ->setProgressMessage('Completed @current of @total.');

    $batch_builder->addOperation([$this, 'processItems'], [
      $items,
      $count,
    ]);

    batch_set($batch_builder->toArray());
  }

  public function processItem($row) {
    $vid = $row['vid'];

    $auto = $this->getTermFromRow($row, Utility::VID_BRANDS);
    $service = $this->getTermFromRow($row, Utility::VID_SERVICES);
    $area = $this->getTermFromRow($row, Utility::VID_AREA);

    if ($vid == Utility::VID_SERVICES) {
      $node = NULL;
      // Если это родительский термин Услуги или это не ссылка, то не создавать ноду, но создать ссылку меню.
      if (!empty($service->parent->target_id) && $service->field_not_link->value != '1') {
        $node = $this->intersection->handleIntersection($auto, $service, $area);
      }
      $menu_link = $this->menuService->checkMenuItem($auto, $service, $area, $node);
    }

    else {
      $node = $this->intersection->handleIntersection($auto, $service, $area);
      $menu_link = $this->menuService->checkMenuItem($auto, $service, $area, $node);
    }
    return $menu_link;
  }

  /**
   * Processor for batch operations.
   */
  public function getTermFromRow($row, $vid) {
    return !empty($row[$vid]['term']) ? $row[$vid]['term'] : NULL;
  }

  /**
   * Processor for batch operations.
   */
  public function processItems($items, $count, array &$context) {
    $limit = 50;

    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['reviews'] = 0;
      $context['sandbox']['max'] = count($items);
    }

    if (empty($context['sandbox']['items'])) {
      $context['sandbox']['items'] = $items;
    }

    $counter = 0;
    if (!empty($context['sandbox']['items'])) {
      // Remove already processed items.
      if ($context['sandbox']['progress'] != 0) {
        array_splice($context['sandbox']['items'], 0, $limit);
      }

      foreach ($context['sandbox']['items'] as $item) {
        if ($counter != $limit) {
          $this->processItem($item);

          $counter++;
          $context['sandbox']['progress']++;

          $context['message'] = $this->t('Now processing term :progress of :count', [
            ':progress' => $context['sandbox']['progress'],
            ':count' => $context['sandbox']['max'],
          ]);

          // Increment total processed item values. Will be used in finished
          // callback.
          $context['results']['processed'] = $context['sandbox']['progress'];
          $context['results']['reviews'] = $context['sandbox']['reviews'];
        }
      }
    }

    // If not finished all tasks, we count percentage of process. 1 = 100%.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Finished callback for batch.
   */
  public function finished($success, $results, $operations) {
    $message = 'Обработано терминов: '. $results['processed'];
    $this->messenger->addStatus($message);
  }

}
