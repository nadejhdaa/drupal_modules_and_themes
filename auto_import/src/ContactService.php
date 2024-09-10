<?php

declare(strict_types=1);

namespace Drupal\auto_import;

use Drupal\comment\CommentManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\node\Entity\Node;
use Drupal\auto_import\TermService;
use Drupal\auto_import\FileService;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\geofield\WktGeneratorInterface;
use Drupal\Core\Url;

/**
 * @todo Add class description.
 */
final class ContactService implements ContactServiceInterface {

  /**
   * Constructs a ContactService object.
   */
  public function __construct(
    private readonly TermService $termService,
    private readonly CommentManagerInterface $commentManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ConfigManagerInterface $configManager,
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DatabaseFileUsageBackend $fileUsage,
    private readonly FileService $fileService,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly WktGeneratorInterface $geofieldWktGenerator,
  ) {}

  /**
   * Get config.
   */
  public function config() {
    return $this->configFactory->get('auto_import.import');
  }

  /**
   * Get node storage.
   */
  public function nodeStorage() {
    return $this->entityTypeManager->getStorage('node');
  }

  /**
   * Load contacts nodes by properties.
   */
  public function loadByProperties($params = []) {
    $entityStorage = $this->nodeStorage();
    $query = $entityStorage->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('type', self::NODE_TYPE_CONTACT);

    if (!empty($params)) {
      foreach ($params as $key => $value) {
        if ($value == 'notExists') {
          $query->notExists($key);
        }
        else {
          $query->condition($key, $value);
        }
      }
    }
    $nids = $query->execute();

    if (!empty($nids)) {
      return $entityStorage->loadMultiple($nids);
    }
    return [];
  }

  /**
   * Find car_service node by field_remote_id.
   */
  public function findByRemoteId($remote_id) {
    $nodes = $this->nodeStorage()
      ->loadByProperties([
        'type' => 'car_service',
        'field_remote_id' => $remote_id,
    ]);

    return !empty($nodes) ? reset($nodes) : FALSE;
  }

  /**
   * Set text field values from row.
   */
  public function setTextFieldsValues($node, $row) {
    $node->set('title', $row['title']);
    $fields = self::FIELDS;
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
    $remote_url = $this->config()->get('base_url');
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
    $remote_url = $this->config()->get('base_url');
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
        $properties = [
          'field_media_image' => $downloaded_file->id(),
        ];
        $medias = $this->fileService->loadMediaByProperties($properties);
        $gallery_title = $this->buildGalleryName($node->label(), $i);
        $media = !empty($medias) ? reset($medias) : $this->fileService->createMedia($downloaded_file, $gallery_title);
        $mids[] = $media->id();
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
    return $node->label() . 'Фотогалерея_' . $i;
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
    }
  }

  /**
   * Create new contact node and add fields.
   */
  public function newContact($row) {
    $data = [
      'type' => 'car_service',
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
    $value = \Drupal::service('geofield.wkt_generator')->WktBuildPoint($point);
    $node->field_address->setValue($value);
    return $node;
  }
}
