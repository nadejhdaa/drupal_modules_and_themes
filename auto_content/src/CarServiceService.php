<?php

declare(strict_types=1);

namespace Drupal\auto_content;

use Drupal\auto_import\TermService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\auto_content\Utility\Utility;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\auto_import\FileService;
use Drupal\geofield\WktGeneratorInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * @todo Add class description.
 */
final class CarServiceService extends NodeService {

  protected $nodeType = Utility::NODE_TYPE_CAR_SERVICE;

  /**
   * Constructs a ContactService object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entity_type_manager,
    private readonly Connection $db_connection,
    private readonly TermService $term_service,
    private readonly ConfigFactoryInterface $config_factory,
    private readonly DatabaseFileUsageBackend $fileUsage,
    private readonly FileService $fileService,
    private readonly WktGeneratorInterface $geofieldWktGenerator,
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->termService = $term_service;
    $this->connection = $db_connection;
  }

  /**
   * Set text field values from row.
   */
  public function setTextFieldsValues($node, $row) {
    $node->set('title', $row['title']);
    $fields = Utility::FIELDS;
    foreach ($fields as $field) {
      if (!empty($row[$field])) {
        if ($field == 'field_address_string') {
          $node->$field->value = strip_tags($row[$field]);
        }
        else {
          $node->$field->value = $row[$field];
        }
      }
    }
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function updateNode($node, $row) {
    $remote_url = $this->configAutoImport()->get('base_url');
    $node = $this->setTextFieldsValues($node, $row);

    // Worker photo.
    if (!empty($row['field_worker_photo'])) {
      $alt = !empty($row['field_worker_fio']) ? $row['field_worker_fio'] : $node->label();
      $title = !empty($row['field_worker_fio']) ? $row['field_worker_fio'] : $node->label();
      $this->checkImageFieldFromRow($row, 'field_worker_photo', $node, $alt, $title);
    }
    else {
      $node->set('field_worker_photo', NULL);
    }

    // Field "field_gallery".
    if (!empty($row['field_gallery'])) {
      $mids = [];

      foreach ($row['field_gallery'] as $key => $row_value) {
        $downloaded_file = $this->fileService->checkFileFromRow($row_value);
        if (!empty($downloaded_file)) {
          $downloaded_file_uri = $downloaded_file->getFileUri();

          // If field_gallery field has values.
          if (!empty($node->get('field_gallery')[$key])) {
            $media = Media::load($node->get('field_gallery')[$key]->target_id);
            $mids[] = $media->id();
            // Get fileuri from current Media.
            $old_file_uri = $this->fileService->getFileUriFromMedia($media);

            // If downloaded file != file in Media.
            if ($downloaded_file_uri != $old_file_uri) {
              $this->fileService->setFileToMedia($downloaded_file, $media);
              // Remove unused file with the same uri.
              $this->fileService->removeUnusedFilesByUri($old_file_uri);
            }
          }
          // If field_gallery is empty.
          else {
            $properties = [
              'field_media_image' => $downloaded_file->id(),
            ];
            $medias = $this->fileService->loadMediaByProperties($properties);
            $gallery_title = $this->buildGalleryName($node->label(), $key);

            $media = !empty($medias) ? reset($medias) : $this->fileService->createMedia($downloaded_file, $gallery_title);

            $mids[] = $media->id();
          }
        }
      }

      $node->set('field_gallery', $mids);
    }
    // If row['field_gallery'] is empty, unset 'field_gallery' for node.
    else {
      $node->set('field_gallery', NULL);
    }

    // Terms.
    $node = $this->setContactTerms($node, $row);

    if (!empty($row['field_address'])) {
      $node = $this->setAddressField($node, $row['field_address']);
    }

    $node->save();
    return $node;
  }

  /**
   * @todo Add method description.
   */
  public function createNode($row) {
    $remote_url = $this->configAutoImport()->get('base_url');
    $node = $this->newContact($row);

    // Field "field_worker_photo".
    if (!empty($row['field_worker_photo'])) {
      $alt = !empty($row['field_worker_fio']) ? $row['field_worker_fio'] : $node->label();
      $title = !empty($row['field_worker_fio']) ? $row['field_worker_fio'] : $node->label();
      $this->checkImageFieldFromRow($row, 'field_worker_photo', $node, $alt, $title);
    }

    // Field "field_gallery".
    if (!empty($row['field_gallery'])) {
      $mids = [];
      foreach ($row['field_gallery'] as $i => $row_value) {
        $downloaded_file = $this->fileService->checkFileFromRow($row_value);
        // Check media if exists Media with the same file.
        if ($downloaded_file) {
          $properties = [
            'field_media_image' => $downloaded_file->id(),
          ];
          $medias = $this->fileService->loadMediaByProperties($properties);
          $gallery_title = $this->buildGalleryName($node->label(), $i);
          $media = !empty($medias) ? reset($medias) : $this->fileService->createMedia($downloaded_file, $gallery_title);
          $mids[] = $media->id();
        }
      }
      // Set Medias to field_gallery.
      if (!empty($mids)) {
        $node->set('field_gallery', $mids);
      }
    }

    $node->save();
    return $node;
  }

  public function buildGalleryName($node_label, $i) {
    return 'Фотогалерея_"' . $node_label . '"_' . $i;
  }

  /**
   * Download file and add it to imagefield.
   */
  public function checkImageFieldFromRow($row, $fieldname, $node, $alt, $title) {
    $downloaded_file = $this->fileService->checkFileFromRow($row['field_worker_photo']);

    // If $fieldname is exists.
    if (!empty($downloaded_file)) {
      $node->field_worker_photo->target_id = $downloaded_file->id();
      $node->field_worker_photo->alt = !empty($row['field_worker_fio']) ? $row['field_worker_fio'] : $node->label();
      $node->field_worker_photo->title = !empty($row['field_worker_fio']) ? $row['field_worker_fio'] : $node->label();
      return TRUE;
    }
    return;
  }

  /**
   * Create new contact node and add fields.
   */
  public function newContact($row) {
    $data = [
      'type' => $this->nodeType,
      'title' => $row['title'],
    ];
    $node = Node::create($data);
    $node = $this->setTextFieldsValues($node, $row);

    // Set node terms.
    $node = $this->setContactTerms($node, $row);

    // Set value to "field_address" field.
    if (!empty($row['field_address'])) {
      $node = $this->setAddressField($node, $row['field_address']);
    }

    return $node;
  }

  /**
   * Set terms to all term reference fields.
   */
  public function setContactTerms($node, $row) {
    $not_found = [];
    dsm($row);
    if (!empty($row['field_facilities'])) {
      $tids = $this->termService->getTids($row['field_facilities'], 'facilities');
      $node->set('field_facilities', $tids);
    }

    if (!empty($row['field_auto'])) {
      $tids = $this->termService->getTids($row['field_auto'], 'auto');
      $node->set('field_auto', $tids);
    }

    if (!empty($row['field_area'])) {
      $tids = $this->termService->getTids($row['field_area'], 'area');
      $node->set('field_area', $tids);
    }

    if (!empty($row['field_services'])) {
      $tids = $this->termService->getServicesTids($row['field_services'], 'services');
      $node->set('field_services', $tids);
    }

    if (!empty($row['field_services_block'])) {
      $tids = $this->termService->getServicesTids($row['field_services_block'], 'services_block');
      $node->set('field_services_block', $tids);
    }

    return $node;
  }

  /**
   * Set longitued and latitude values to address field..
   */
  public function setAddressField($node, $data) {
    $point = [ $data['lon'], 'lat' => $data['lat'] ];
    $value = $this->geofieldWktGenerator->WktBuildPoint($point);
    $node->field_address->setValue($value);
    return $node;
  }

  /**
   * Set longitued and latitude values to address field..
   */
  public function getRandomNidsWithReviews($count) {
    $connection = $this->connection;
    $query = $connection->select('node__field_car_service', 'c');
    $query->fields('c', ['field_car_service_target_id']);
    $query->range(0, $count);
    $query->orderRandom();
    $nids = $query->distinct()->execute()->fetchCol();
    return $nids;
  }

}
