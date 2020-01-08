# php-kafka-consumer

PHP library to consume Kafka messages

## Installation

Open a command console, enter your project directory and execute:

```console
$ composer require ticketswap/kafka-consumer
```

After that, add the bundle to your kernel:

```php
// config/bundles.php
return [
    // ...
    TicketSwap\TicketSwapKafkaConsumerBundle::class => ['all' => true],
];
```

Then, make sure you define the following parameters:

```yaml
kafka_broker_list: 'kafka:9092'
kafka_sasl_enabled: 0
kafka_sasl_username: ''
kafka_sasl_password: ''
kafka_prefix: ''
```

## Usage

