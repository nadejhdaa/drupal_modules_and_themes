<?php

namespace Drupal\test_redirect\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\test_redirect\Entity\TestRedirectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TestRedirectController.
 *
 *  Returns responses for Test redirect routes.
 */
class TestRedirectController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Test redirect revision.
   *
   * @param int $test_redirect_revision
   *   The Test redirect revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($test_redirect_revision) {
    $test_redirect = $this->entityTypeManager()->getStorage('test_redirect')
      ->loadRevision($test_redirect_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('test_redirect');

    return $view_builder->view($test_redirect);
  }

  /**
   * Page title callback for a Test redirect revision.
   *
   * @param int $test_redirect_revision
   *   The Test redirect revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($test_redirect_revision) {
    $test_redirect = $this->entityTypeManager()->getStorage('test_redirect')
      ->loadRevision($test_redirect_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $test_redirect->label(),
      '%date' => $this->dateFormatter->format($test_redirect->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Test redirect.
   *
   * @param \Drupal\test_redirect\Entity\TestRedirectInterface $test_redirect
   *   A Test redirect object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(TestRedirectInterface $test_redirect) {

    $account = $this->currentUser();
    $test_redirect_storage = $this->entityTypeManager()->getStorage('test_redirect');

    $langcode = $test_redirect->language()->getId();
    $langname = $test_redirect->language()->getName();
    $languages = $test_redirect->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $test_redirect->label()]) : $this->t('Revisions for %title', ['%title' => $test_redirect->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("test revert all redirect revisions") || $account->hasPermission('test administer redirect entities')));
    $delete_permission = (($account->hasPermission("test delete all redirect revisions") || $account->hasPermission('test administer redirect entities')));

    $rows = [];

    $vids = $test_redirect_storage->revisionIds($test_redirect);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\test_redirect\Entity\TestRedirectInterface $revision */
      $revision = $test_redirect_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];

      // Use revision link to link to revisions that are not active.
      $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
      if ($vid != $test_redirect->getRevisionId()) {
        $link = Link::fromTextAndUrl($date, new Url('entity.test_redirect.revision', [
          'test_redirect' => $test_redirect->id(),
          'test_redirect_revision' => $vid,
        ]))->toString();
      }
      else {
        $link = $test_redirect->toLink($date)->toString();
      }

      $row = [];
      $column = [
        'data' => [
          '#type' => 'inline_template',
          '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
          '#context' => [
            'date' => $link,
            'username' => $this->renderer->renderPlain($username),
            'message' => [
              '#markup' => $revision->getRevisionLogMessage(),
              '#allowed_tags' => Xss::getHtmlTagList(),
            ],
          ],
        ],
      ];
      $row[] = $column;

      if ($latest_revision) {
        $row[] = [
          'data' => [
            '#prefix' => '<em>',
            '#markup' => $this->t('Current revision'),
            '#suffix' => '</em>',
          ],
        ];
        foreach ($row as &$current) {
          $current['class'] = ['revision-current'];
        }
        $latest_revision = FALSE;
      }
      else {
        $links = [];
        if ($revert_permission) {
          $links['revert'] = [
            'title' => $this->t('Revert'),
            'url' => $has_translations ?
            Url::fromRoute('entity.test_redirect.translation_revert', [
              'test_redirect' => $test_redirect->id(),
              'test_redirect_revision' => $vid,
              'langcode' => $langcode,
            ]) :
            Url::fromRoute('entity.test_redirect.revision_revert', [
              'test_redirect' => $test_redirect->id(),
              'test_redirect_revision' => $vid,
            ]),
          ];
        }

        if ($delete_permission) {
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('entity.test_redirect.revision_delete', [
              'test_redirect' => $test_redirect->id(),
              'test_redirect_revision' => $vid,
            ]),
          ];
        }

        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }

      $rows[] = $row;
    }

    $build['test_redirect_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
