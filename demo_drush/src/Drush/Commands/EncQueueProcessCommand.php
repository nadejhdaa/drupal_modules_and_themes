<?php

declare(strict_types=1);

namespace Drupal\demo_drush\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Component\Datetime\TimeInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;
use Drush\Formatters\FormatterTrait;
use Drush\Drush;

#[AsCommand(
  name: 'demo-queue-process',
  description: 'Process the queue by name.',
  aliases: ['demo-qp'],
)]
class demoQueueProcessCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;

  /**
   * {@selfdoc}
   */
  public function __invoke(
    SymfonyStyle $io,
    #[Argument('queue_name')]
    string $queue_name,
  ): int {
    $queue = \Drupal::queue($queue_name);

    $start_timestamp = time();
    $date_formatter = \Drupal::service('date.formatter');

    \Drupal::logger('drush_demo_queue_process')->notice("Старт выполнения очереди $queue_name ({$queue->numberOfItems()})...");

    $queue->garbageCollection();

    $ping_interval = 20;

    $i = 0;
    while ($item = $queue->claimItem()) {
      $queue->releaseItem($item);
      $i ++;

      $process = Drush::drush(Drush::aliasManager()->getSelf(), 'queue:run', [$queue_name], ['time-limit' => $ping_interval]);
      $process
        ->setTimeout(NULL)
        ->setIdleTimeout(NULL)
        ->start($process->showRealtime());
      $process->wait();
    }

    $io->text("Очередь $queue_name выполнена за {$date_formatter->formatDiff($start_timestamp, time())}");

    \Drupal::logger('drush_demo_queue_process')->notice("Очередь $queue_name выполнена за {$date_formatter->formatDiff($start_timestamp, time())}, обработано $i элементов");

    return self::SUCCESS;
  }

}
