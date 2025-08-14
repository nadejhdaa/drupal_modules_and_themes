<?php

declare(strict_types=1);

namespace Drupal\demo_service_data\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\demo_service_data\DemoServiceDataInterface;

/**
 * Defines the service data entity class.
 *
 * @ContentEntityType(
 *   id = "demo_service_data",
 *   label = @Translation("Service data"),
 *   label_collection = @Translation("Service datas"),
 *   label_singular = @Translation("service data"),
 *   label_plural = @Translation("service datas"),
 *   label_count = @PluralTranslation(
 *     singular = "@count service datas",
 *     plural = "@count service datas",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\demo_service_data\DemoServiceDataListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\demo_service_data\Form\DemoServiceDataForm",
 *       "edit" = "Drupal\demo_service_data\Form\DemoServiceDataForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\demo_service_data\Routing\DemoServiceDataHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "demo_service_data",
 *   admin_permission = "administer demo_service_data",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/enc-service-data",
 *     "add-form" = "/enc-service-data/add",
 *     "canonical" = "/enc-service-data/{demo_service_data}",
 *     "edit-form" = "/enc-service-data/{demo_service_data}",
 *     "delete-form" = "/enc-service-data/{demo_service_data}/delete",
 *     "delete-multiple-form" = "/admin/content/enc-service-data/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.demo_service_data.settings",
 * )
 */
final class DemoServiceData extends ContentEntityBase implements DemoServiceDataInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Service status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['service'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service "service" code'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', FALSE);

    $fields['pu'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service "pu" code'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', FALSE);

    $fields['pu1'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service "pu1" code'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', FALSE);

    $fields['du'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service "du" code'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
