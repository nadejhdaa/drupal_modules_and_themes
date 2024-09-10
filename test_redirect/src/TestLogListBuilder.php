<?php

namespace Drupal\test_redirect;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Test log entities.
 *
 * @ingroup test_redirect
 */
class TestLogListBuilder extends EntityListBuilder {

  public function render() {
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this
        ->buildHeader(),
      '#title' => $this
        ->getTitle(),
      '#rows' => [],
      '#empty' => $this
        ->t('There have been no redirects yet', [
        '@label' => $this->entityType
          ->getPluralLabel(),
      ]),
      '#cache' => [
        'contexts' => $this->entityType
          ->getListCacheContexts(),
        'tags' => $this->entityType
          ->getListCacheTags(),
      ],
    ];
    foreach ($this->load() as $entity) {
      if (!$entity->get('logs')->isEmpty()) {
        foreach ($entity->get('logs') as $key => $log) {
          $logs[] = $log;
        }
        $logs = array_reverse($logs);

        foreach ($logs as $key => $log) {
          $id = $entity->id() . '_' . $key;
          $row = $this->buildLogRow($entity, $log, $key);
          $build['table']['#rows'][$id] = $row;
        }
      }
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }
    return $build;
  }

  public function buildLogRow($entity, $log, $key) {
    if (!empty($log->getValue())) {
      $log = unserialize($log->getValue()['value']);
      $date = !empty($log['time']) ? \Drupal::service('date.formatter')->format($log['time'], 'custom', 'd.m.Y H:i') : '';
      $row = [
        'id' => $entity->id(),
        'date' => $date,
        'type' => $log['type'],
        'response_code' => $log['response_code'],
        'source' => $log['source'],
        'url' => $log['url'],
        'status_code' => $log['status_code'],

      ];
    }
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['date'] = $this->t('Date and time');
    $header['type'] = $this->t('Status');
    $header['response_code'] = $this->t('Response code');
    $header['source'] = $this->t('From');
    $header['url'] = $this->t('To');
    $header['status_code'] = $this->t('Redirect status');


    return $header + parent::buildHeader();
  }
}
