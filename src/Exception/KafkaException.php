<?php

declare(strict_types=1);

namespace TicketSwap\Kafka\Exception;

use Exception;

final class KafkaException extends Exception
{
    /**
     * @var int
     */
    private $errorCode;

    private function __construct(string $message, int $errorCode)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }

    public static function withErrorAndReason(int $errorCode, string $reason) : self
    {
        return new self(
            sprintf('Kafka error: %d - %s (reason: %s)', $errorCode, rd_kafka_err2str($errorCode), $reason),
            $errorCode
        );
    }

    public function isCausedByTimeout() : bool
    {
        return $this->errorCode === RD_KAFKA_RESP_ERR__TIMED_OUT;
    }

    public function getErrorCode() : int
    {
        return $this->errorCode;
    }
}
