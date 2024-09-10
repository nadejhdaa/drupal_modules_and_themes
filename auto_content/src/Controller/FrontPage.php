<?php

declare(strict_types=1);

namespace Drupal\auto_content\Controller;

use Drupal\auto_content\BlockService;
use Drupal\auto_content\CarServiceService;
use Drupal\auto_content\IntersectionService;
use Drupal\auto_content\YandexReviewService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\auto_content\Utility\Utility;

/**
 * Returns responses for Auto content routes.
 */
final class FrontPage extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly BlockService $blockService,
    private readonly IntersectionService $intersectionService,
    private readonly CarServiceService $carServiceService,
    private readonly YandexReviewService $yandexReviewService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('auto_content.block_service'),
      $container->get('auto_content.intersection_service'),
      $container->get('auto_content.car_service_service'),
      $container->get('auto_content.yandex_review_service'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    return [
      '#theme' => 'front_content',
      '#data' => [
        'top_text_uuid' => $this->getTopTextUuid(),
        'front_text_uuid' => $this->blockService->getBlockUuidByType('front_text'),
        'front_img_top' => $this->getTopImgBlockUuid(),
        'car_services_all' => $this->getFrontNids(),
        'random_services' => $this->getRandomServicesNids(),
        'random_services_nids' => $this->getRandomServicesNids(),
        'advantages_block_uuid' => $this->blockService->getBlockUuidByType('advantages'),
        'our_service_block_uuid' => $this->blockService->getBlockUuidByType('our_services'),
        'review_nids' => $this->yandexReviewService->getRandomNids(6),
        'models_block_uuid' => $this->blockService->getBlockUuidByType('models'),
      ],
    ];
  }

  /**
   * Get block with type = "basic" with top text.
   */
  public function getTopTextUuid() {
    $block = $this->blockService->getStorage()->load(Utility::BLOCK_FRONT_TOP_TEXT_ID);
    if (!empty($block)) {
      return $block->uuid();
    }
  }

  /**
   * Get "top_photos" uuid.
   */
  public function getTopImgBlockUuid() {
    $block = $this->blockService->loadOneBlockByType('top_photos');
    return $block->uuid();
  }

  public function getFrontNids() {
    $fields = [
      'field_auto' => Utility::AUDI_TID,
    ];
    $nids = $this->carServiceService->getIdsByFields($fields);
    return $nids;
  }

  public function getRandomServicesNids() {
    $count = 6;
    return $this->carServiceService->getRandomNidsWithReviews($count);
  }


}
