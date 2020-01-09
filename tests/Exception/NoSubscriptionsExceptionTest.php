<?php

namespace TicketSwap\Kafka\Exception;

use PHPUnit\Framework\TestCase;

class NoSubscriptionsExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_be_created_with_a_forgotten_method_name() : void
    {
        // Arrange
        $methodName = 'subscribeToTopics';

        // Act
        $exception = NoSubscriptionsException::notInitializedWithMethod($methodName);

        // Assert
        $this->assertEquals(
            'No subscriptions registered for consumer. Please call "subscribeToTopics()" first.',
            $exception->getMessage()
        );
    }
}
