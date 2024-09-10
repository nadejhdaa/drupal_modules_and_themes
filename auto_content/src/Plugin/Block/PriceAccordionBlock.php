<?php

declare(strict_types=1);

namespace Drupal\auto_content\Plugin\Block;

use Drupal\auto_import\FileService;
use Drupal\auto_import\TermService;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\PathMatcher;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Render\Markup;
use Drupal\auto_content\Utility\Utility;

/**
 * Provides a price accordion block.
 *
 * @Block(
 *   id = "auto_content_price_accordion",
 *   admin_label = @Translation("Price Accordion"),
 *   category = @Translation("Custom"),
 * )
 */
final class PriceAccordionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  // Техническое обслуживание, Подвеска, Диагностика автомобиля, Электрооборудование.
  const PRICE_FRONT_TIDS = [
    22023,
    22019,
    22009,
    22022
  ];

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly FileService $autoImportFileService,
    private readonly TermService $autoImportTermService,
    private readonly PathMatcher $pathMatcher,
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
      $container->get('current_route_match'),
      $container->get('auto_import.file_service'),
      $container->get('auto_import.term_service'),
      $container->get('path.matcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'header' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['header'] = [
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#title' => $this->t('Example'),
      '#default_value' => $this->configuration['header'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['header'] = $form_state->getValue('header')['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $route_name = $this->routeMatch->getRouteName();
    return [
      '#theme' => 'accordion_price',
      '#data' => [
        'header' => $this->getHeader(),
        'items' => $this->getPriceData($route_name),
        'accordion' => $this->getPriceData($route_name),
      ]
    ];
  }

  public function getHeader() {
    return [
      '#markup' => $this->configuration['header'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPriceData($route_name) {
    $tids = $this->getParentTids();
    $data = $this->getAccordionData($tids);
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentTids() {
    //////////////////// Front page ////////////////////
    if ($this->pathMatcher->isFrontPage()) {
      return self::PRICE_FRONT_TIDS;
    }

    //////////////////// Intersection and Price page ////////////////////
    else {
      $tids = [];

      if ($this->routeMatch->getRouteName() == 'entity.node.canonical') {
        $nid = $this->routeMatch->getRawParameter('node');
        $node = Node::load($nid);

        if ($node->getType() == 'intersection') {

          $price_names = self::PRICE_NAMES;

          if (!$node->get('field_service')->isEmpty()) {
            $service_tid = $node->field_service->target_id;
            $service = Term::load($service_tid);
            $service_parent = $this->termService->getParent($service);
            if (empty($service_parent)) {
              $service_parent = $service;
            }
          }

          if (!empty($service_parent)) {
            $service_parent_name = $service_parent->getName();
            $service_price_block_term = $this->termService->findTermByName($service_parent_name, Utility::VID_SERVICES);
            if (!empty($service_price_block_term)) {
              $price_names[0] = $service_parent_name;
            }
          }

          $manager = $this->entityTypeManager->getStorage('taxonomy_term');
          $terms = $manager->loadTree(Utility::VID_PRICE_ACCORDION, 0, 1, FALSE);

          foreach ($terms as $key => $term) {
            if (in_array($term->name, $price_names)) {
              $tids[] = $term->tid;
              unset($terms[$key]);
            }
          }
          $random = array_rand($terms, 2);

          foreach ($random as $key) {
            $tids[] = $terms[$key]->tid;
          }
        }

        elseif ($node->getType() == 'page') {
          $title = FALSE;
          $current_path = \Drupal::service('path.current')->getPath();
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);

          if ($alias == '/price') {
            $manager = $this->entityTypeManager->getStorage('taxonomy_term');
            $terms = $manager->loadTree(Utility::VID_PRICE_ACCORDION, 0, 1, FALSE);
            foreach ($terms as $term) {
              $tids[] = $term->tid;
            }
          }
        }
      }
      return $tids;
    }
  }

  public function getAccordionData($tids) {
    $data = [];
    if (!empty($tids)) {
      $taxonomy_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree(Utility::VID_PRICE_ACCORDION, 0, 4, TRUE);
      $data = $this->getAccordionHeaders($taxonomy_tree, $tids);
      $data = $this->getAccordionBody($taxonomy_tree, $data);

      foreach ($data as $key => $value) {
        $data[$value['weight']] = $value;
        unset($data[$key]);
      }
      ksort($data);
    }
    return $data;
  }

  public function getAccordionHeaders($taxonomy_tree, $tids) {
    $data = [];
    foreach ($taxonomy_tree as $key => $term) {
      if (in_array($term->id(), $tids)) {
        $data[$term->id()] = [
          'accordion_header' => [
            'title' => $term->getName(),
          ],
          'accordion_body' => [],
          'weight' => array_search($term->id(), $tids),
        ];

        if (!$term->get('field_icon')->isEmpty()) {
          $data[$term->id()]['accordion_header']['icon'] = $term->get('field_icon')->entity->createFileUrl();
        }
      }
    }

    return $data;
  }

  public function getAccordionBody($taxonomy_tree, $data) {
    $items_chunks = [];

    foreach ($taxonomy_tree as $key => $term) {
      $tid = $term->id();
      $parent_tid = $term->parent->target_id;

      if ($parent_tid > 0) {
        if (array_key_exists($parent_tid, $data)) {
          $exists[$term->id()] = $parent_tid;
        }
      }
      else {
        if (array_key_exists($tid, $data)) {
          $exists[$tid] = $tid;
        }
      }
    }

    foreach ($taxonomy_tree as $key => $term) {
      $tid = $term->id();
      $parent_tid = $term->parent->target_id;

      if (array_key_exists($parent_tid, $exists)) {
        $superparent = $exists[$parent_tid];

        if ($term->get('field_price')->isEmpty()) {
          $item = '<div class="price-item">';
          $item .= $term->getName();
          $item .= '<div class="price-item__price"></div></div>';
        }
        else {
          $item = '<div class="price-item ps-3">';
          $item .= '-' . $term->getName();
          $item .= '<div class="price-item__underline"></div>';
          $item .= '<div class="price-item__price">от ' . $term->field_price->value . ' руб.</div>';
        }

        $items_chunks[$superparent][$term->id()] = Markup::create($item);
      }
    }

    foreach ($items_chunks as $key => $items) {
      $data[$key]['accordion_body'] = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $items,
        '#attributes' => ['class' => ['accordion__price-list']],
        '#wrapper_attributes' => ['class' => ['accordion__price-wrapper']],
      ];
    }

    return $data;
  }

}
