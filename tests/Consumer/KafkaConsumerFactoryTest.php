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
            'group.id',
            false,
            null,
            null
        );

        // Assert
        self::assertInstanceOf(\RdKafka\KafkaConsumer::class, $consumer);
    }

    /**
     * @test
     */
    public function it_should_create_a_correct_consumer_if_no_ssl_certificate_is_found() : void
    {
        // Arrange
        $consumer = KafkaConsumerFactory::create(
            '',
            'group.id',
            true,
            'foo',
            'bar'
        );

        // Assert
        self::assertInstanceOf(\RdKafka\KafkaConsumer::class, $consumer);
    }
}
