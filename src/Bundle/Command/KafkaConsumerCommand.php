<?php

declare(strict_types=1);

namespace TicketSwap\Kafka\Bundle\Command;

use LongRunning\Core\Cleaner;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RdKafka\Exception;
use RdKafka\Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TicketSwap\Kafka\Consumer\KafkaConsumer;
use TicketSwap\Kafka\Exception\NoSubscriptionsException;
use TicketSwap\Kafka\Subscription\KafkaSubscription;

class KafkaConsumerCommand extends Command
{
    protected KafkaConsumer $consumer;
    protected LoggerInterface $logger;
    protected bool $run = true;
    protected Cleaner $cleaner;

    /**
     * @var iterable<KafkaSubscription>
     */
    protected iterable $subscriptions;

    /**
     * @param KafkaSubscription[] $subscriptions
     */
    public function __construct(
        KafkaConsumer $consumer,
        ?LoggerInterface $logger,
        Cleaner $cleaner,
        iterable $subscriptions
    ) {
        parent::__construct();

        $this->consumer      = $consumer;
        $this->logger        = $logger ?? new NullLogger;
        $this->cleaner       = $cleaner;
        $this->subscriptions = $subscriptions;
    }

    protected function configure() : void
    {
        $this->setName('ticketswap:kafka-consumer');
        $this->addOption('topic', null, InputOption::VALUE_REQUIRED, 'The Kafka topic to consume.');
        $this->addOption('all-topics', null,InputOption::VALUE_NONE, 'The Kafka topic to consume.');
    }

    /**
     * @throws Exception
     * @throws NoSubscriptionsException
     */
    public function execute(InputInterface $input, OutputInterface $output) : int
    {
        if ($input->getOption('all-topics') === false && $input->getOption('topic') === null) {
            $output->writeln('<error>Please specify the topic to consume (--topic=<topic>) or use --all-topics to consume all topics.</error>');

            return 1;
        }

        if ($output->isVerbose() === true) {
            $output->writeln('Starting Kafka consumer');
        }

        pcntl_async_signals(true);
        pcntl_sigprocmask(SIG_BLOCK, [SIGIO]);
        pcntl_signal(SIGTERM, fn() => $this->stopCommand($output));
        pcntl_signal(SIGQUIT, fn() => $this->stopCommand($output));
        pcntl_signal(SIGINT, fn() => $this->stopCommand($output));

        $topicNames = [];
        foreach ($this->subscriptions as $subscription) {
            if ($input->getOption('all-topics') === false) {
                if ($subscription->subscribesTo($input->getOption('topic')) === false) {
                    continue;
                }
            }

            if ($output->isVerbose() === true) {
                $output->writeln(sprintf('Subscribing to topic "%s"', $subscription->getTopicName()));
            }
            $topicNames[] = $subscription->getTopicName();
        }

        if (count($topicNames) === 0) {
            $output->writeln(sprintf('<error>No subscriptions found matching topic "%s"</error>', $input->getOption('topic')));

            return 1;
        }

        $this->consumer->subscribeToTopics($topicNames);

        if ($output->isVerbose() === true) {
            $output->writeln(sprintf('Subscribed to %d topic(s) and consuming messages...', count($topicNames)));
        }

        while ($this->run === true) {
            if ($output->isDebug() === true) {
                $output->writeln('Started another round of consuming...');
            }

            $message = $this->consumer->consume();

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    if ($output->isDebug() === true) {
                        $output->writeln('Got a message, handling it now...');
                    }

                    $this->handleMessage($message);
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    if ($output->isDebug() === true) {
                        $output->writeln('End of partition or timeout encountered...');
                    }

                    break;
                case RD_KAFKA_RESP_ERR__ALL_BROKERS_DOWN:
                    $this->logNotice('All brokers are down, stopping consumer...');

                    $this->stopCommand($output);
                    break;
                default:
                    $output->writeln($message->errstr());
                    $this->logError(
                        'KafkaConsumerCommand: Kafka error',
                        [
                            'error' => $message->errstr(),
                        ]
                    );
            }
        }

        return 0;
    }

    private function handleMessage(Message $message) : void
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->subscribesTo($message->topic_name) === false) {
                continue;
            }

            if (extension_loaded('newrelic') === true) {
                newrelic_set_appname('Worker');
                newrelic_start_transaction('Worker');
                newrelic_name_transaction(sprintf('Kafka topic: %s', $message->topic_name));
                newrelic_background_job();
            }

            try {
                $subscription->handle($message);
                $this->consumer->commit($message);
            } catch (Throwable $exception) {
                // Don't let exceptions in the subscriptions stop the command.
                // If the ->handle fails, it won't commit and try again
                $this->logError(
                    'KafkaConsumerCommand: Exception thrown in handleMessage',
                    [
                        'exception' => $exception,
                        'payload'   => $message->payload,
                        'topic'     => $message->topic_name,
                        'message'   => $message,
                    ]
                );
            }

            if (extension_loaded('newrelic') === true) {
                newrelic_end_transaction();
            }

            break;
        }

        $this->cleaner->cleanUp();
    }

    public function stopCommand(OutputInterface $output) : void
    {
        if ($output->isVerbose() === true) {
            $output->writeln('Shutting down Kafka Consumer');
        } else {
            $this->logNotice('Shutting down Kafka Consumer');
        }

        $this->run = false;

        posix_kill(posix_getpid(), SIGIO);
        pcntl_signal_dispatch();
    }

    /**
     * @param array<string, string> $context
     */
    protected function logNotice(string $message, array $context = array()) : void
    {
        $this->logger->notice($message, $context);
    }

    /**
     * @param array<string, string> $context
     */
    protected function logError(string $message, array $context = array()) : void
    {
        $this->logger->error($message, $context);
    }
}
