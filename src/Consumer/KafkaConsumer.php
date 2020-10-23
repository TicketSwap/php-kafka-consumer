<?php

declare(strict_types=1);

namespace TicketSwap\Kafka\Consumer;

use RdKafka\Exception;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RdKafka\Message;
use TicketSwap\Kafka\Exception\KafkaException;
use TicketSwap\Kafka\Exception\NoSubscriptionsException;

class KafkaConsumer
{
    /**
     * @var RdKafkaConsumer
     */
    private $consumer;

    /**
     * Wait 5 seconds before timing out.
     */
    private const CONSUMER_TIMEOUT_MS = 5000;

    public function __construct(RdKafkaConsumer $consumer)
    {
        $this->consumer = $consumer;
    }

    /**
     * @param string[] $topicNames
     */
    public function subscribeToTopics(array $topicNames) : void
    {
        if (empty($topicNames) === false) {
            $this->consumer->subscribe($topicNames);
        }
    }

    /**
     * @throws NoSubscriptionsException
     * @throws KafkaException
     * @throws Exception
     */
    public function consume() : Message
    {
        if ($this->hasSubscriptions() === false) {
            throw NoSubscriptionsException::notInitializedWithMethod('subscribeToTopics');
        }

        try {
            return $this->consumer->consume(self::CONSUMER_TIMEOUT_MS);
        } catch (KafkaException $exception) {
            if ($exception->isCausedByTimeout() === true) {
                /*
                 * If the `consume()` method stops because of a timeout it sometimes seems to return a message with
                 * that error, and sometimes in calls the defined callback (see KafkaConsumerFactory::create()),
                 * which throws a KafkaException. In the latter case, this method transforms the exception to a message
                 * with the right error to make sure the caller can use it in a consistent manner.
                 */
                $message      = new Message();
                $message->err = $exception->getErrorCode();

                return $message;
            }

            throw $exception;
        }
    }

    /**
     * @throws Exception
     */
    public function commit(Message $message) : void
    {
        $this->consumer->commit($message);
    }

    private function hasSubscriptions() : bool
    {
        return count($this->consumer->getSubscription()) !== 0;
    }
}
