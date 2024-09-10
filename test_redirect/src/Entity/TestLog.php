<?php

namespace Drupal\test_redirect\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\test_redirect\Entity\TestRedirect;

/**
 * Defines the Test log entity.
 *
 * @ingroup test_redirect
 *
 * @ContentEntityType(
 *   id = "test_log",
 *   label = @Translation("Test log"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\test_redirect\TestLogListBuilder",
 *     "views_data" = "Drupal\test_redirect\Entity\TestLogViewsData",
 *     "translation" = "Drupal\test_redirect\TestLogTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\test_redirect\Form\TestLogForm",
 *       "add" = "Drupal\test_redirect\Form\TestLogForm",
 *       "edit" = "Drupal\test_redirect\Form\TestLogForm",
 *       "delete" = "Drupal\test_redirect\Form\TestLogDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\test_redirect\TestLogHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\test_redirect\TestLogAccessControlHandler",
 *   },
 *   base_table = "test_log",
 *   data_table = "test_log_field_data",
 *   translatable = TRUE,
 *   admin_permission = "test administer log entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/redirect/test-redirects/log/{test_log}",
 *     "add-form" = "/admin/config/search/redirect/test-redirects/log/add",
 *     "delete-form" = "/admin/config/search/redirect/test-redirects/log/{test_log}/delete",
 *     "collection" = "/admin/config/search/redirect/test-redirects/log",
 *   }
 * )
 */
class TestLog extends ContentEntityBase implements TestLogInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('label', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addLog($data) {
    $count = count($this->logs->getValue());
    if (is_array($data)) {
      $data = serialize($data);
    }
    $this->logs[$count] = $data;
    return $this;
  }

  /**
   * Load value of test_redirect_source.
   */
  public function getSourceUrl() {
    $test_redirect = $this->loadTarget();
    $source = $test_redirect->getSourceUrl();
    return $source;
  }

  /**
   * Load target test_redirect entity.
   */
  public function loadTarget() {
    if (!empty($this->target->target_id)) {
      $target_id = $this->target->target_id;
      $test_redirect = TestRedirect::load($target_id);
      return $test_redirect;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setRevisionable(TRUE)
      ->setDescription(t('The user ID of the node author.'))
      ->setDefaultValueCallback('\Drupal\test_redirect\Entity\TestRedirect::getCurrentUserId')
      ->setSettings([
        'target_type' => 'user',
    ]);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['target'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The target logging entity Test redirect.'))
      ->setRevisionable(FALSE)
      ->setSetting('target_type', 'test_redirect')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])

      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);


    $fields['logs'] = BaseFieldDefinition::create('test_data')
      ->setLabel(t('Logs'))
      ->setDescription(t('The log data.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);


    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
