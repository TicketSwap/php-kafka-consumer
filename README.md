# php-kafka-consumer

PHP library to consume Kafka messages

## Installation

Add this private repository to your composer.json file:

```yaml
"repositories": [
  {
    "type": "vcs",
    "url": "http://github.com/ticketswap/php-kafka-consumer"
  }
]
```

Open a command console, enter your project directory and execute:

```console
$ composer require ticketswap/kafka-consumer
```

After that, add the bundle to your kernel:

```php
// config/bundles.php
return [
    // ...
    TicketSwap\Kafka\Bundle\TicketSwapKafkaConsumerBundle::class => ['all' => true],
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

First, implement one or more subscriptions by implementing the `TicketSwap\Kafka\Subscription\KafkaSubscription` interface.
Next, tag those subscriptions with the `kafka-consumer.subscriptions` tag:
```yaml
  Acme\Subscriptions\:
    resource: '../../Subscription/*'
    tags: ['kafka-consumer.subscription']
```

After that, everything should be ready to start consuming messages.

The bundle comes with a CLI command that you can use as a worker command:

```console
console ticketswap:kafka-consumer
```
