<?php

namespace TicketSwap\Kafka\Consumer;

use Mockery;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RdKafka\Message;
use TicketSwap\Kafka\Exception\KafkaException;
use TicketSwap\Kafka\Exception\NoSubscriptionsException;

class KafkaConsumerTest extends Mockery\Adapter\Phpunit\MockeryTestCase
{
    /**
     * @var Mockery\MockInterface|RdKafkaConsumer
     */
    private $rdKafkaConsumer;

    /**
     * @var KafkaConsumer
     */
    private $kafkaConsumer;

    protected function setUp() : void
    {
        $this->rdKafkaConsumer = Mockery::mock(RdKafkaConsumer::class);
        $this->kafkaConsumer   = new KafkaConsumer($this->rdKafkaConsumer);
    }

    /**
     * @test
     */
    public function it_should_not_consume_when_there_are_no_subscribers() : void
    {
        // Arrange
        $this->rdKafkaConsumer->shouldReceive('getSubscription')->andReturn([]);

        // Assert
        $this->expectException(NoSubscriptionsException::class);

        // Act
        $this->kafkaConsumer->consume();
    }

    /**
     * @test
     */
    public function it_should_consume() : void
    {
        // Arrange
        $message             = new Message();
        $message->topic_name = 'foo';
        $message->payload    = 'bar';

        $this->rdKafkaConsumer->shouldReceive('getSubscription')->andReturn(['topic']);
        $this->rdKafkaConsumer->shouldReceive('consume')->andReturn($message);

        // Act
        $resultingMessage = $this->kafkaConsumer->consume();

        // Assert
        $this->rdKafkaConsumer->shouldHaveReceived('consume');
        $this->assertEquals($message, $resultingMessage);
    }

    /**
     * @test
     */
    public function it_should_return_a_proper_message_for_timeout_errors() : void
    {
        // Arrange
        $this->rdKafkaConsumer->shouldReceive('getSubscription')->andReturn(['topic']);
        $this->rdKafkaConsumer->shouldReceive('consume')->andThrow(
            KafkaException::withErrorAndReason(RD_KAFKA_RESP_ERR__TIMED_OUT, 'foo')
        );

        // Act
        $message = $this->kafkaConsumer->consume();

        // Assert
        $this->assertEquals(RD_KAFKA_RESP_ERR__TIMED_OUT, $message->err);
    }
}
