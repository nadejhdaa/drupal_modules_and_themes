<?php

namespace Drupal\test_redirect\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Test redirect revision.
 *
 * @ingroup test_redirect
 */
class TestRedirectRevisionDeleteForm extends ConfirmFormBase {


  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The Test redirect revision.
   *
   * @var \Drupal\test_redirect\Entity\TestRedirectInterface
   */
  protected $revision;

  /**
   * The Test redirect storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $testRedirectStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->testRedirectStorage = $container->get('entity_type.manager')->getStorage('test_redirect');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->time = $container->get('datetime.time');
    $instance->entity_type_manager = $container->get('entity_type.manager');
    return $instance;

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_redirect_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.test_redirect.version_history', ['test_redirect' => $this->revision->getRevisionId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $test_redirect_revision = NULL) {
    $this->revision = $this->entity_type_manager->getStorage('test_redirect')->loadRevision($test_redirect_revision);
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->TestRedirectStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Test redirect: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()->addMessage(t('Revision from %revision-date of Test redirect %title has been deleted.', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.test_redirect.canonical',
       ['test_redirect' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {test_redirect_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.test_redirect.version_history',
         ['test_redirect' => $this->revision->id()]
      );
    }
  }

}
