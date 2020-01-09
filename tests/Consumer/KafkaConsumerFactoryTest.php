<?php

namespace TicketSwap\Kafka\Consumer;

use PHPUnit\Framework\TestCase;

class KafkaConsumerFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_create_a_correct_consumer() : void
    {
        // Arrange
        $consumer = KafkaConsumerFactory::create(
            '',
            false,
            null,
            null
        );

        // Assert
        self::assertInstanceOf(\RdKafka\KafkaConsumer::class, $consumer);
    }
}
