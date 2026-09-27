<?php

namespace Drupal\enc_extendmedia\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class MediaGetIdController extends ControllerBase {

  public function getId(string $uuid): JsonResponse {

    $entities = $this->entityTypeManager()
      ->getStorage('media')
      ->loadByProperties(['uuid' => $uuid]);

    $media = reset($entities);

    if (!$media) {
      throw new NotFoundHttpException();
    }

    return new JsonResponse(['id' => $media->id()]);
  }
}
