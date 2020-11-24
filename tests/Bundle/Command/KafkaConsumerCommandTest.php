<?php

namespace TicketSwap\Kafka\Bundle\Command;

use LongRunning\Core\Cleaner;
use Mockery;
use Psr\Log\LoggerInterface;
use RdKafka\Message;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TicketSwap\Kafka\Consumer\KafkaConsumer;
use TicketSwap\Kafka\Subscription\KafkaSubscription;

class KafkaConsumerCommandTest extends Mockery\Adapter\Phpunit\MockeryTestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var KafkaConsumer|Mockery\MockInterface
     */
    private $kafkaConsumer;

    /**
     * @var KafkaSubscription|Mockery\MockInterface
     */
    private $subscription;

    /**
     * @var Cleaner|Mockery\MockInterface
     */
    private $cleaner;

    /**
     * @var LoggerInterface|Mockery\MockInterface
     */
    private $logger;

    protected function setUp() : void
    {
        $this->application   = new Application();
        $this->kafkaConsumer = Mockery::mock(KafkaConsumer::class);
        $this->subscription  = Mockery::mock(KafkaSubscription::class);
        $this->cleaner       = Mockery::mock(Cleaner::class);
        $this->logger        = Mockery::mock(LoggerInterface::class);

        $this->logger->shouldReceive('info');

        $this->application->add(
            new KafkaConsumerCommand(
                $this->kafkaConsumer,
                $this->logger,
                $this->cleaner,
                [$this->subscription]
            )
        );
    }

    /**
     * @test
     */
    public function it_should_call_the_right_subscription() : void
    {
        // Arrange
        $message             = new Message();
        $message->err        = RD_KAFKA_RESP_ERR_NO_ERROR;
        $message->topic_name = 'event-prioritisation';

        $this->subscription->shouldReceive('getTopicName')->andReturn('event-prioritisation');

        $this->kafkaConsumer->shouldReceive('subscribeToTopics');
        $this->kafkaConsumer->shouldReceive('consume')->andReturn($message)->once();
        $this->kafkaConsumer->shouldReceive('commit');

        $this->subscription->shouldReceive('subscribesTo')->andReturn(true);
        $this->subscription->shouldReceive('handle');

        // Fail so the while loop stops
        $message2             = new Message();
        $message2->err        = RD_KAFKA_RESP_ERR__ALL_BROKERS_DOWN;
        $message2->topic_name = 'stream-event-traction-prediction';
        $this->kafkaConsumer->shouldReceive('consume')->andReturn($message2);

        // Error handling for failing message
        $this->logger->shouldReceive('error');
        $this->logger->shouldReceive('notice')->with('All brokers are down, stopping consumer...', []);
        $this->logger->shouldReceive('notice')->with('Shutting down Kafka Consumer', []);

        $this->cleaner->shouldReceive('cleanUp');

        // Act
        $command       = $this->application->find('ticketswap:kafka-consumer');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), '--all-topics' => true]);

        // Assert
        $this->subscription->shouldHaveReceived('handle');
        $this->kafkaConsumer->shouldHaveReceived('commit');
    }

    /**
     * @test
     */
    public function it_should_not_call_subscriptions_when_topic_does_not_match() : void
    {
        // Arrange
        $message             = new Message();
        $message->err        = RD_KAFKA_RESP_ERR_NO_ERROR;
        $message->topic_name = 'event-dexter';

        $this->subscription->shouldReceive('getTopicName')->andReturn('event-prioritisation');
        $this->kafkaConsumer->shouldReceive('subscribeToTopics');

        $this->subscription->shouldReceive('subscribesTo')->andReturn(false);
        $this->subscription->shouldReceive('handle');

        // Fail so the while loop stops
        $message2             = new Message();
        $message2->err        = RD_KAFKA_RESP_ERR__ALL_BROKERS_DOWN;
        $message2->topic_name = 'stream-event-traction-prediction';
        $this->kafkaConsumer->shouldReceive('consume')->andReturn($message2);

        // Error handling for failing message
        $this->logger->shouldReceive('error');
        $this->logger->shouldReceive('notice')->with('All brokers are down, stopping consumer...', []);
        $this->logger->shouldReceive('notice')->with('Shutting down Kafka Consumer', []);

        // Act
        $command       = $this->application->find('ticketswap:kafka-consumer');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        // Assert
        $this->subscription->shouldNotHaveReceived('handle');
        $this->kafkaConsumer->shouldNotHaveReceived('commit');
    }
}
