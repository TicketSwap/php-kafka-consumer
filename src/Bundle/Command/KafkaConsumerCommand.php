<?php

declare(strict_types=1);

namespace CliBundle\Command\Kafka;

use LongRunning\Core\Cleaner;
use Psr\Log\LoggerInterface;
use RdKafka\Exception;
use RdKafka\Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TicketSwap\Kafka\Consumer\KafkaConsumer;
use TicketSwap\Kafka\Exception\NoSubscriptionsException;
use TicketSwap\Kafka\Subscriptions\KafkaSubscription;

class KafkaConsumerCommand extends Command
{
    /**
     * @var KafkaConsumer
     */
    private $consumer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var iterable<KafkaSubscription>
     */
    private $subscriptions;

    /**
     * @var bool
     */
    private $run = true;

    /**
     * @var Cleaner
     */
    private $cleaner;

    /**
     * @var string
     */
    private $environment;

    /**
     * @param KafkaSubscription[] $subscriptions
     */
    public function __construct(
        KafkaConsumer $consumer,
        LoggerInterface $logger,
        Cleaner $cleaner,
        iterable $subscriptions,
        string $environment
    ) {
        parent::__construct();

        $this->consumer      = $consumer;
        $this->logger        = $logger;
        $this->cleaner       = $cleaner;
        $this->subscriptions = $subscriptions;
        $this->environment   = $environment;
    }

    protected function configure() : void
    {
        $this->setName('ticketswap:kafka-consumer');
    }

    /**
     * @throws Exception
     * @throws NoSubscriptionsException
     */
    public function execute(InputInterface $input, OutputInterface $output) : ?int
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, [$this, 'stopCommand']);
        pcntl_signal(SIGQUIT, [$this, 'stopCommand']);
        pcntl_signal(SIGINT, [$this, 'stopCommand']);

        $topicNames = [];

        foreach ($this->subscriptions as $subscription) {
            // To allow for individual scaling, the consumer will only listen to 1 topic in production.
            // This is decided by a system environment variable
            $topicName = (string) getenv('KAFKA_TOPIC');

            if ($this->environment === 'prod' && $subscription->subscribesTo($topicName) === true) {
                $topicNames[] = $subscription->getTopicName();
            } elseif ($this->environment === 'dev') {
                $topicNames[] = $subscription->getTopicName();
            }
        }

        $this->consumer->subscribeToTopics($topicNames);

        while ($this->run === true) {
            $message = $this->consumer->consume();

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->handleMessage($message);
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;
                case RD_KAFKA_RESP_ERR__ALL_BROKERS_DOWN:
                    $this->stopCommand();
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

    public function stopCommand() : void
    {
        $this->logger->notice('Shutting down Kafka Consumer');

        $this->run = false;
    }

    protected function logError($message, array $context = array()) : void
    {
        $this->logger->error($message, $context);
    }
}