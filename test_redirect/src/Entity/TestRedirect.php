<?php

namespace Drupal\test_redirect\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\link\LinkItemInterface;

/**
 * Defines the Test redirect entity.
 *
 * @ingroup test_redirect
 *
 * @ContentEntityType(
 *   id = "test_redirect",
 *   label = @Translation("Test redirect"),
 *   handlers = {
 *     "storage" = "Drupal\test_redirect\TestRedirectStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\test_redirect\Entity\TestRedirectViewsData",
 *     "translation" = "Drupal\test_redirect\TestRedirectTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\test_redirect\Form\TestRedirectForm",
 *       "add" = "Drupal\test_redirect\Form\TestRedirectForm",
 *       "edit" = "Drupal\test_redirect\Form\TestRedirectForm",
 *       "delete" = "Drupal\test_redirect\Form\TestRedirectDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\test_redirect\TestRedirectHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\test_redirect\TestRedirectAccessControlHandler",
 *   },
 *   base_table = "test_redirect",
 *   data_table = "test_redirect_field_data",
 *   revision_table = "test_redirect_revision",
 *   revision_data_table = "test_redirect_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "test administer redirect entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
*   revision_metadata_keys = {
*     "revision_user" = "revision_uid",
*     "revision_created" = "revision_timestamp",
*     "revision_log_message" = "revision_log"
*   },
 *   links = {
 *     "canonical" = "/admin/config/search/redirect/test-redirects/{test_redirect}/log",
 *     "add-form" = "/admin/config/search/redirect/test-redirects/add",
 *     "edit-form" = "/admin/config/search/redirect/test-redirects/{test_redirect}/edit",
 *     "delete-form" = "/admin/config/search/redirect/test-redirects/{test_redirect}/delete",
 *     "version-history" = "/admin/config/search/redirect/test-redirects/{test_redirect}/revisions",
 *     "revision" = "/admin/config/search/redirect/test-redirects/{test_redirect}/revisions/{test_redirect_revision}/view",
 *     "revision_revert" = "/admin/config/search/redirect/test-redirects/{test_redirect}/revisions/{test_redirect_revision}/revert",
 *     "revision_delete" = "/admin/config/search/redirect/test-redirects/{test_redirect}/revisions/{test_redirect_revision}/delete",
 *     "translation_revert" = "/admin/config/search/redirect/test-redirects/{test_redirect}/revisions/{test_redirect_revision}/revert/{langcode}",
 *   }
 * )
 */
class TestRedirect extends EditorialContentEntityBase implements TestRedirectInterface {

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
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly,
    // make the test_redirect owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Create referenced Log entity.
    if ($update) {
      $log = \Drupal::entityTypeManager()->getStorage('test_log')->load($this->getLog());
    }
    else {
      $log = \Drupal::entityTypeManager()->getStorage('test_log')->create(
        [
          'user_id' => $this->getOwnerId(),
          'target' => $this->id(),
        ]
      );
    }

    $source_url = $this->getSourceUrl();

    $log_label = t('Log: @source_url (@redirect_id)', [
      '@source_url' => $source_url,
      '@redirect_id' => $this->id(),
    ]);

    $log->setName($log_label->__toString());
    $log->save();

    if (empty($this->getLog())) {
      $this->set('log', $log->id());
      $this->save();
    }
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
  public function setName($label) {
    $this->set('label', $label);
    return $this;
  }

  /**
   * Gets the redirect URL.
   *
   * @return \Drupal\Core\Url
   *   The redirect URL.
   */
  public function getRedirectUrl() {
    return $this->get('test_redirect_redirect')->get(0)->value;
  }

  /**
   * Gets the redirect URL.
   *
   * @return \Drupal\Core\Url
   *   The redirect URL.
   */
  public function getFallbackUrl() {
    return $this->get('test_redirect_fallback')->get(0)->value;
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
   * Gets the redirect status code.
   *
   * @return int
   *   The redirect status code.
   */
  public function getStatusCode() {
    return $this->get('status_code')->value;
  }

  /**
   * Gets the redirect test ID.
   *
   * @return int
   *   The redirect status code.
   */
  public function getLog() {
    return $this->get('log')->target_id;
  }

  /**
   * Gets Source redirect url.
   *
   * @return string
   *   Source redirect url.
   */
  public function getSourceUrl() {
    return $this->get('test_redirect_source')->value;
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

    $fields['test_redirect_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source redirect url'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue(NULL)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -7,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['test_redirect_redirect'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Redirect url'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -6,
      ]);

    $fields['test_redirect_fallback'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Fallback redirect url'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -5,
      ]);

    $fields['status_code'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status code'))
      ->setDescription(t('The redirect status code.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -4,
      ]);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setRevisionable(TRUE)
      ->setSettings(array(
        'default_value' => '',
        'text_processing' => 0,
      ))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -3,
        'settings' => ['rows' => 3],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'text_default',
        'weight' => -3,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setRevisionable(TRUE)
      ->setDescription(t('Redirect is active.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => -1,
        ]);


    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'medium',
        ],
        'weight' => 0,
    ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['log'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Log entity'))
      ->setDescription(t('The log entity to store log.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'test_log')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => 2,
        'settings' => [
          'view_mode' => 'default',
          'link' => 'false',
        ],
      ]);

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

}
