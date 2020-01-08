<?php

declare(strict_types=1);

namespace TicketSwap\Kafka\Exception;

use Exception;

final class NoSubscriptionsException extends Exception
{
    public static function notInitializedWithMethod(string $methodName) : self
    {
        return new self(sprintf('No subscriptions registered for consumer. Please call "%s()" first.', $methodName), 0);
    }
}
