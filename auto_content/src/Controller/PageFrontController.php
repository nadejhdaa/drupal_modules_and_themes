<?php

declare(strict_types=1);

namespace Drupal\auto_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\auto_content\BlockService;
use Drupal\auto_content\IntersectionService;
use Drupal\auto_content\CarServiceService;
use Drupal\auto_content\YandexReviewService;


/**
 * Returns responses for Auto content routes.
 */
final class PageFrontController extends ControllerBase {

  /**
   * The auto_base.menu service.
   *
   * @var \Drupal\auto_content\BlockService
   */
  protected $blockService;

  /**
   * The auto_base.menu service.
   *
   * @var \Drupal\auto_content\IntersectionService
   */
  protected $intersectionService;

  /**
   * The auto_content.car_service_service.
   *
   * @var \Drupal\auto_content\CarServiceService
   */
  protected $carServiceService;

  /**
   * The auto_content.yandex_review_service.
   *
   * @var \Drupal\auto_content\YandexReviewService
   */
  protected $yandexReviewService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    BlockService $block_service,
    IntersectionService $intersection_service,
    CarServiceService $car_service_service,
    YandexReviewService $yandex_review_service,
  ) {
    $this->blockService = $block_service;
    $this->intersectionService = $intersection_service;
    $this->carServiceService = $car_service_service;
    $this->yandexReviewService = $yandex_review_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('auto_content.block_service'),
      $container->get('auto_content.intersection_service'),
      $container->get('auto_content.car_service_service'),
      $container->get('auto_content.yandex_review_service')
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    return [
      '#theme' => 'front_content',
      '#data' => [
        'front_text_uuid' => $this->getBlockUuidByType('front_text'),
        'front_img_top' => $this->getTopImgBlock(),
        'car_services_all' => $this->getFrontNids(),
        'random_services' => $this->getRandomServicesNids(),
        'random_services_nids' => $this->getRandomServicesNids(),
        'advantages_block_uuid' => $this->getBlockUuidByType('advantages'),
        'our_service_block_uuid' => $this->getBlockUuidByType('our_services'),
        'review_nids' => $this->yandexReviewService->getRandomNids(6),
        'models_block_uuid' => $this->getBlockUuidByType('models'),
      ],
      // '#data' => [
      //   'nids' => $nids,
      //   'nids_2' => $nids_2,
      //   'title' => $title,
      //   'brand_tid' => $brand_tid,
      //   'nids_all' => $nids_all,
      //   'nids_review' => $nids_review,
      //   'front_text' => $front_text,
      //   'models_block' => $models_block,
      //   'our_services' => $our_services_block,
      //   'top_bg' => $top_bg,
      //   'img_top' => $img_top,
      // ],
    ];
  }


  public function getTopImgBlock() {
    $block = $this->blockService->loadOneBlockByType('top_photos');
    return $block->uuid();
  }

  public function getFrontNids() {
    $fields = [
      'field_auto' => 26,
    ];
    $nids = $this->carServiceService->getIdsByFields($fields);
    return $nids;
  }

  public function getRandomServicesNids() {
    $count = 6;
    return $this->carServiceService->getRandomNids($count);
  }


  public function getBlockUuidByType($type) {
    $cid = 'front:' . $type . '_block_uuid';
    if ($cache = \Drupal::cache()->get($cid)) {
      $block_uuid = $cache->data;
    }
    if (empty($block_uuid)) {
      $block_uuid = $this->blockService->getOneBlockUuid($type);
      \Drupal::cache()->set($cid, $block_uuid);
    }
    return $block_uuid;
  }

  public function getRandomReviewsNids() {
    $count = 4;
    return $this->carServiceService->getRandomNids($count);
  }






}
