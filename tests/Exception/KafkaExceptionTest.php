<?php

namespace TicketSwap\Kafka\Exception;

use PHPUnit\Framework\TestCase;

class KafkaExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_create_a_new_instance() : void
    {
        // Arrange
        $errorCode = RD_KAFKA_RESP_ERR__TIMED_OUT;
        $reason    = 'host timed out';

        // Act
        $exception = KafkaException::withErrorAndReason($errorCode, $reason);

        // Assert
        $this->assertEquals('Kafka error: -185 - Local: Timed out (reason: host timed out)', $exception->getMessage());
        $this->assertEquals($errorCode, $exception->getErrorCode());
    }

    /**
     * @test
     */
    public function it_should_return_true_when_the_error_is_caused_by_a_timeout() : void
    {
        // Arrange
        $errorCode = RD_KAFKA_RESP_ERR__TIMED_OUT;
        $reason    = 'host timed out';

        // Act
        $exception = KafkaException::withErrorAndReason($errorCode, $reason);

        // Assert
        $this->assertTrue($exception->isCausedByTimeout());
    }

    /**
     * @test
     */
    public function it_should_return_false_when_the_error_is_not_caused_by_a_timeout() : void
    {
        // Arrange
        $errorCode = RD_KAFKA_RESP_ERR__MSG_TIMED_OUT;
        $reason    = 'took too long to process message';

        // Act
        $exception = KafkaException::withErrorAndReason($errorCode, $reason);

        // Assert
        $this->assertFalse($exception->isCausedByTimeout());
    }
}
