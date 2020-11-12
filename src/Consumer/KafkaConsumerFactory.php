<?php

declare(strict_types=1);

namespace TicketSwap\Kafka\Consumer;

use RdKafka\Conf;
use RdKafka\Exception;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use TicketSwap\Kafka\Exception\KafkaException;

final class KafkaConsumerFactory
{
    private const TIMEOUT_MS               = '5000';
    private const SASL_SCRAM               = 'sasl_scram';
    private const SASL_SSL                 = 'sasl_ssl';
    private const SCRAM_SHA_256            = 'SCRAM-SHA-256';
    private const SMALLEST                 = 'smallest';
    private const SSL_CERTIFICATE_LOCATION = '/etc/ssl/certs/cloudkarafka.ca';

    /**
     * @param string $brokerList   Comma-separated string of broker addresses
     * @param bool   $saslEnabled  Whether SASL security is enabled for the Kafka cluster (uses SASL_SCRAM)
     * @param string $saslUsername Username for SASL security as provided by the cluster host
     * @param string $saslPassword Password for SASL security as provided by the cluster host
     *
     * @throws Exception
     *
     * @see https://docs.confluent.io/current/kafka/authentication_sasl/authentication_sasl_scram.html
     */
    public static function create(
        string $brokerList,
        string $groupId,
        bool $saslEnabled,
        ?string $saslUsername,
        ?string $saslPassword
    ) : RdKafkaConsumer {
        $configuration = new Conf();

        if ($saslEnabled === true) {
            $configuration->set('builtin.features', self::SASL_SCRAM);
            $configuration->set('security.protocol', self::SASL_SSL);
            $configuration->set('ssl.ca.location', self::SSL_CERTIFICATE_LOCATION);
            $configuration->set('sasl.mechanisms', self::SCRAM_SHA_256);
            $configuration->set('sasl.username', $saslUsername);
            $configuration->set('sasl.password', $saslPassword);
        }

        $configuration->setErrorCb(static function ($kafka, $error, $reason) : void {
            throw KafkaException::withErrorAndReason($error, $reason);
        });

        // When a re-balance occurs in the Kafka pool it should not stop the process.
        // Instead, it should either assign, revoke or do nothing
        $configuration->setRebalanceCb(static function ($rk, $error, $partitions) : void {
            switch ($error) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    $rk->assign($partitions);
                    break;
                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    $rk->assign(null);
                    break;
                case RD_KAFKA_RESP_ERR_REBALANCE_IN_PROGRESS:
                    // Do nothing
                    break;
            }
        });

        // Configure the group.id. All consumers with the same group.id will consume
        // different partitions. Using sasl username as suffix for CloudKarafka
        $actualGroupId = sprintf('%s%s', $saslUsername ? $saslUsername . '-' : '', $groupId);
        $configuration->set('group.id', $actualGroupId);

        // Set timeout
        $configuration->set('socket.timeout.ms', self::TIMEOUT_MS);

        // Initial list of Kafka brokers
        if (empty($brokerList) === false) {
            $configuration->set('metadata.broker.list', $brokerList);
        }

        // Set where to start consuming messages when there is no initial offset in
        // offset store or the desired offset is out of range.
        // 'smallest': start from the beginning
        $configuration->set('auto.offset.reset', self::SMALLEST);

        // Sets a signal to listen for to terminate internal processes. This prevents
        // lingering processes to block shutdown.
        $configuration->set('internal.termination.signal', (string) SIGIO);

        $consumer = new RdKafkaConsumer($configuration);

        return $consumer;
    }
}
