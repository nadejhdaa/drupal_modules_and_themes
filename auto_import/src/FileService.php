<?php

declare(strict_types=1);

namespace Drupal\auto_import;

use Drupal\auto_import\Client\ClientBaseInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\Core\File\FileUrlGenerator;

/**
 * @todo Add class description.
 */
final class FileService {

  /**
   * Constructs a FileService object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientBaseInterface $client,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly DatabaseFileUsageBackend $fileUsageService,
    private readonly FileUrlGenerator $fileUrlGenerator
  ) {}

  /**
   * Get config.
   */
  public function config() {
    return $this->configFactory->get('auto_import.import');
  }

  /**
   * Get File Storage.
   */
  public function getStorage() {
    return $this->entityTypeManager->getStorage('file');
  }

  /**
   * Get Media Storage.
   */
  public function getMediaStorage() {
    return $this->entityTypeManager->getStorage('media');
  }

  /**
   * Load files by properties.
   */
  public function loadByProperties($properties) {
    if (!empty($properties)) {
      return $this->getStorage()->loadByProperties($properties);
    }
  }

  /**
   * Load files by properties.
   */
  public function loadMediaByProperties($properties) {
    if (!empty($properties)) {
      return $this->getMediaStorage()->loadByProperties($properties);
    }
  }

  /**
   * Find file by uri. If it not exists create it from uri.
   */
  public function createFileFromUrl($url) {
    $file_uri = $this->saveExternalFile($url);

    if (!empty($file_uri)) {
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $file_uri]);
      if (!empty($files)) {
        $file = reset($files) ?: NULL;
        return $file;
      }
      else {
        return $this->createFile($file_uri);
      }
    }
    return;
  }

  /**
   * Download and save file from external url.
   */
  public function saveExternalFile($url) {
    $filename = $this->fileSystem->basename($url);
    $filename_renamed = $this->checkFileName($this->decodeFileName($filename));

    $parsed_url = parse_url($url);
    $file_uri = str_replace(['/sites/default/files/', $filename], ['public://', $filename_renamed], $parsed_url['path']);

    $response = $this->client->getRequest($url, 1);
    if ($response->getStatusCode() == 200) {
      $data = $response->getBody();
      $this->saveFile($data, $file_uri);

      $file = File::create([
        'filename' => $filename,
        'uri' => $file_uri,
        'status' => 1,
        'uid' => 1,
      ]);
      $file->save();
      return $file;
    }
    return FALSE;
  }

  /**
   * Save file from Base string.
   */
  public function saveFile($data, $file_uri, $managed = FALSE, $replace = FileSystemInterface::EXISTS_REPLACE) {
    $directory = $this->fileSystem->dirname($file_uri);

    $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $real_path = $this->fileSystem->realpath($file_uri);

    $this->fileSystem->saveData($data, $real_path, $replace);
    return $real_path;
  }

  /**
   * Get decoded filename string.
   */
  public function decodeFileName($filename) {
    return urldecode($filename);
  }

  /**
   * Remove empty space from file name.
   */
  public function checkFileName($filename) {
    return str_replace(' ', '_', ($this->decodeFileName($filename)));
  }

  /**
   * Create file with uri.
   */
  public function createFile($file_uri) {
    $new_file = File::create([
      'uri' => $file_uri,
      'status' => 1,
    ]);

    $new_file->save();
    return $new_file;
  }

  /**
   * Add file to Media entity.
   */
  public function setFileToMedia($file, $media) {
    if(!$media->get('field_media_image')->isEmpty()) {
      $media_file = $media->field_media_image->entity;
      $count = $this->getFileUsageCount($media_file);

      if ($count == 1) {
        $media->field_media_image->target_id = $file->id();
        $media->save();
        $media_file->delete();
      }
      else {
        $media = $this->createMedia($file);
      }
    }
    return $media;
  }

  /**
   * Create new Media entity.
   */
  public function createMedia($file, $title = NULL) {
    $media = Media::create([
      'name' => !empty($title) ? $title : $file->getFilename(),
      'bundle' => 'image',
      'uid' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => !empty($title) ? $title : $file->getFilename(),
        'title' => $file->getFilename(),
      ],
    ]);
    $media->save();
    $this->setFileUsage($file, $media);
    return $media;
  }

  /**
   * Set file usage to any entity.
   */
  public function setFileUsage($file, $entity) {
    $entity_type = $entity->getEntityType()->id();
    $list = $this->fileUsageService->listUsage($file);
    if (empty($list['file'][$entity_type][$entity->id()])) {
      $this->fileUsageService->add($file, 'file', $entity_type, $entity->id());
    }
  }

  /**
   * Get file usage list.
   */
  public function getFileUsage($file) {
    $list = $this->fileUsageService->listUsage($file);
    return $list;
  }

  /**
   * Get file usage list.
   */
  public function getFileUsageCount($file) {
    $count = 0;
    $list = $this->fileUsageService->listUsage($file);
    if (!empty($list['file'])) {
      foreach ($list['file'] as $type => $data) {
        $count += reset($data);
      }
    }
    return $count;
  }

  /**
   * Remove unused files with some uri.
   */
  public function removeUnusedFilesByUri($uri) {
    $files = $this->loadByProperties(['uri' => $uri]);
    if (!empty($files)) {
      foreach ($files as $file) {
        $list = $this->fileUsageService->listUsage($file);
        if (empty($list)) {
          $file->delete();
        }
      }
    }
  }

  /**
   * Get File entity from Media entity.
   */
  public function getFileFromMedia($media) {
    $fid = $media->field_media_image->target_id;
    return File::load($fid);
  }

  /**
   * Get file uri from Media entity.
   */
  public function getFileUriFromMedia($media) {
    $file = $this->getFileFromMedia($media);
    return $file->getFileUri();
  }

  /**
   * Get file uri from file path.
   */
  public function buildUriFromPath($row_string) {
    return str_replace('/sites/default/files/', 'public://', rawurldecode($row_string));
  }

  /**
   * Remove unused files with some uri.
   */
  public function checkFileFromRow($row_string) {
    $file = FALSE;
    $uri = $this->buildUriFromPath($row_string);
    $relativePathToFile = $this->fileUrlGenerator->generateString($uri);
    $real_path = $this->fileSystem->realpath($uri);

    if (!file_exists($real_path)) {
      $url = $this->config()->get('base_url') . $row_string;
      $response = $this->client->getRequest($url, 1);

      if ($response->getStatusCode() == 200) {
        $data = $response->getBody();
        $this->saveFile($data, $uri);
        $file = $this->createFile($uri);
      }
    }
    else {
      $files = $this->loadByProperties(['uri' => $uri]);
      if (empty($files)) {
        $file = $this->createFile($uri);
      }
      else {
        $file = reset($files);
      }
    }
    return $file;
  }
}
