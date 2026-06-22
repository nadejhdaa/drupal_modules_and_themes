<?php

declare(strict_types=1);

namespace Drupal\demo_entity_and_queue\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\demo_entity_and_queue\EncServiceDataPrInterface;

/**
 * Defines the enc service data from getpr entity class.
 *
 * @ContentEntityType(
 *   id = "demo_entity_and_queue",
 *   label = @Translation("Enc service data from getPr"),
 *   label_collection = @Translation("Enc service data from getPrs"),
 *   label_singular = @Translation("enc service data from getpr"),
 *   label_plural = @Translation("enc service data from getprs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count enc service data from getprs",
 *     plural = "@count enc service data from getprs",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\demo_entity_and_queue\EncServiceDataPrListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\demo_entity_and_queue\Form\EncServiceDataPrForm",
 *       "edit" = "Drupal\demo_entity_and_queue\Form\EncServiceDataPrForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\demo_entity_and_queue\Routing\EncServiceDataPrHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "demo_entity_and_queue",
 *   admin_permission = "administer demo_entity_and_queue",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/enc-service-data-pr",
 *     "add-form" = "/enc-service-data-pr/add",
 *     "canonical" = "/enc-service-data-pr/{demo_entity_and_queue}",
 *     "edit-form" = "/enc-service-data-pr/{demo_entity_and_queue}",
 *     "delete-form" = "/enc-service-data-pr/{demo_entity_and_queue}/delete",
 *     "delete-multiple-form" = "/admin/content/enc-service-data-pr/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.demo_entity_and_queue.settings",
 * )
 */
final class EncServiceDataPr extends ContentEntityBase implements EncServiceDataPrInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
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

    $fields['label_long'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Label long'))
      ->setRequired(FALSE)
      ->setSetting('rows', 2)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['duv'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Duv'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255)
      ->setDescription(t('Example: "1.308". This is a unique value.'))
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

    $fields['du'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Du'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDescription(t('Example: "B01.058.01029"'))
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

    $fields['service'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Service code'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255)
      ->setDescription(t('Example: "ФABAABAABFABa"'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
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

    $fields['price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Price'))
      ->setRequired(FALSE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setDescription(t('Example: "21800.00"'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'number_decimal',
        'weight' => -5,
        'thousand_separator' => ' ',
        'decimal_separator' => '.',
        'scale' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
