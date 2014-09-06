# RabbitMQ Tools

## Installation

The recommended way to install Swarrot is through
[Composer](http://getcomposer.org/). Require the
`odolbeau/rabbit-mq-admin-toolkit` package into your `composer.json` file:

```json
{
    "require": {
        "odolbeau/rabbit-mq-admin-toolkit": "@stable"
    }
}
```

**Protip:** you should browse the
[`odolbeau/rabbit-mq-admin-toolkit`](https://packagist.org/packages/odolbeau/rabbit-mq-admin-toolkit)
page to choose a stable version to use, avoid the `@stable` meta constraint.

## Usage

You can create / update vhosts with the following command:

    ./rabbit vhost:mapping:create conf/vhost/events.yml

You can change all connection informations with options. Launch `./console
vhost:create -h` to have more informations.

You can launch the vhost creation even if the vhost already exist. Nothing will
be deleted (and it will not impact workers).

## Configuration

You can use the followings parameters for configuring an exchange:

* `with dl`: if set to true, all queues in the current vhost will be
  automatically configured to have a dl (with name: `{queueName}_dl`). Of
  course, the exchange `dl` will be created.
* `with_unroutable`: is set to true, an `unroutable` exchange will be created
  and all  others ones will be configured to move unroutable messages to this
  one. The `unroutable` exchange is a fanout exchange and a `unroutable` queue
  is bind on it.
