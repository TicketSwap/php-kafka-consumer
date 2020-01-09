<?php

declare(strict_types=1);

namespace TicketSwap\Kafka\Subscription;

use RdKafka\Message;

interface KafkaSubscription
{
    public function getTopicName() : string;

    public function handle(Message $message) : void;

    public function subscribesTo(string $topicName) : bool;
}
